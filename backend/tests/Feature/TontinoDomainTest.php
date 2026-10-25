<?php

namespace Tests\Feature;

use App\Enums\LedgerType;
use App\Exceptions\InsufficientFundsException;
use App\Models\Contribution;
use App\Models\Cooperative;
use App\Models\Loan;
use App\Models\LoanGuarantor;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Tontine;
use App\Models\User;
use App\Services\ContributionService;
use App\Services\LedgerService;
use App\Services\LoanService;
use App\Services\PaymentSimulator;
use App\Services\PayoutService;
use App\Services\TontineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TontinoDomainTest extends TestCase
{
    use RefreshDatabase;

    private Cooperative $coop;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create();
        $this->coop = Cooperative::create(['name' => 'Test', 'slug' => 'test', 'invite_code' => 'TEST1', 'settings' => Cooperative::DEFAULTS, 'created_by' => $admin->id]);
        $this->member($admin, 'admin');
    }

    private function member(?User $u = null, string $role = 'member'): Membership
    {
        $u ??= User::factory()->create();

        return Membership::create(['cooperative_id' => $this->coop->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active', 'member_number' => 'M-'.str_pad((string) (Membership::count() + 1), 4, '0', STR_PAD_LEFT)]);
    }

    private function tontine(int $members = 3): array
    {
        $ms = collect(range(1, $members))->map(fn () => $this->member());
        $t = Tontine::create(['cooperative_id' => $this->coop->id, 'name' => 'T', 'type' => 'rotative', 'contribution_amount' => 10000, 'frequency' => 'weekly', 'start_date' => today()->subWeeks(2), 'max_members' => 12, 'penalty_percent' => 5, 'status' => 'draft']);
        app(TontineService::class)->addMembers($t, $ms->pluck('id')->all());

        return [$t, $ms];
    }

    public function test_ledger_transfer_is_atomic_and_consistent(): void
    {
        $l = app(LedgerService::class);
        $a = $l->accountOf($this->member());
        $b = $l->accountOf($this->member());
        $l->credit($a, 50000, LedgerType::Deposit);
        $l->transfer($a, $b, 20000, LedgerType::Adjustment);
        $this->assertSame(30000, $a->refresh()->balance);
        $this->assertSame(20000, $b->refresh()->balance);
        $this->assertSame(30000, (int) $a->entries()->first()->balance_after);
        $this->expectException(InsufficientFundsException::class);
        try {
            $l->transfer($a, $b, 99999, LedgerType::Adjustment);
        } finally {
            $this->assertSame(30000, $a->refresh()->balance);
            $this->assertSame(20000, $b->refresh()->balance);
        }
    }

    public function test_draw_creates_unique_positions_cycles_and_contributions_once(): void
    {
        [$t, $ms] = $this->tontine(4);
        $order = app(TontineService::class)->draw($t);
        $this->assertSame([1, 2, 3, 4], $order->pluck('position')->sort()->values()->all());
        $this->assertSame(4, $t->cycles()->count());
        $this->assertSame(16, Contribution::count());
        $this->assertCount(4, $t->cycles()->pluck('beneficiary_membership_id')->unique());
        $this->expectException(\DomainException::class);
        app(TontineService::class)->draw($t);
    }

    public function test_members_cannot_be_added_after_the_draw(): void
    {
        [$t] = $this->tontine(3);
        app(TontineService::class)->draw($t);
        app(TontineService::class)->activate($t);
        $this->expectException(\DomainException::class);
        app(TontineService::class)->addMembers($t, [$this->member()->id]);
    }

    public function test_payment_simulator_success_pays_contribution_and_failure_writes_nothing(): void
    {
        [$t, $ms] = $this->tontine(3);
        app(TontineService::class)->draw($t);
        app(TontineService::class)->activate($t);
        $c = Contribution::where('membership_id', $ms[0]->id)->orderBy('id')->first();
        $sim = app(PaymentSimulator::class);
        $ok = $sim->initiate($ms[0], 'contribution', 'mtn', '0197000001', $c->total(), $c);
        $this->assertSame('success', $ok->refresh()->status);
        $this->assertSame('paid', $c->refresh()->status);
        $ledger = app(LedgerService::class);
        $this->assertSame(10000, $ledger->potOf($t)->balance);
        $c2 = Contribution::where('membership_id', $ms[1]->id)->orderBy('id')->first();
        $ko = $sim->initiate($ms[1], 'contribution', 'moov', '0197000000', $c2->total(), $c2);
        $this->assertSame('failed', $ko->refresh()->status);
        $this->assertSame('pending', $c2->refresh()->status);
        $this->assertSame(0, $ledger->accountOf($ms[1])->balance);
        $again = $sim->process($ok);
        $this->assertSame('success', $again->status);
        $this->assertSame(10000, $ledger->potOf($t)->refresh()->balance);
    }

    public function test_late_flag_adds_penalty_and_payout_requires_ready_cycle(): void
    {
        [$t, $ms] = $this->tontine(3);
        $svc = app(TontineService::class);
        $svc->draw($t);
        $svc->activate($t);
        $this->assertGreaterThan(0, app(ContributionService::class)->flagLate());
        $this->assertSame(500, Contribution::where('status', 'late')->first()->penalty_amount);
        $cycle = $t->cycles()->first();
        $this->expectException(\DomainException::class);
        app(PayoutService::class)->payout($cycle);
    }

    public function test_payout_moves_pot_to_beneficiary_when_all_paid(): void
    {
        [$t, $ms] = $this->tontine(3);
        $svc = app(TontineService::class);
        $svc->draw($t);
        $svc->activate($t);
        $ledger = app(LedgerService::class);
        $cycle = $t->cycles()->first();
        foreach ($cycle->contributions as $c) {
            $ledger->credit($ledger->accountOf($c->membership), 10000, LedgerType::Deposit);
            app(ContributionService::class)->payFromWallet($c);
        }
        $this->assertSame('ready', $cycle->refresh()->status);
        app(PayoutService::class)->payout($cycle);
        $this->assertSame(30000, $ledger->accountOf($cycle->beneficiary)->refresh()->balance);
        $this->assertSame('paid_out', $cycle->refresh()->status);
    }

    public function test_loan_rules_approval_disbursement_and_repayment(): void
    {
        $svc = app(LoanService::class);
        $ledger = app(LedgerService::class);
        $ledger->credit($ledger->fundOf($this->coop), 1000000, LedgerType::Adjustment);
        $manager = $this->member(null, 'treasurer');
        $borrower = $this->member();
        $g = $this->member();
        $baseTontine = Tontine::create(['cooperative_id' => $this->coop->id, 'name' => 'Base prêt', 'type' => 'savings', 'contribution_amount' => 20000, 'frequency' => 'monthly', 'start_date' => today(), 'max_members' => 12, 'penalty_percent' => 5, 'status' => 'active']);
        $baseCycle = $baseTontine->cycles()->create(['number' => 1, 'due_date' => today(), 'beneficiary_membership_id' => $borrower->id, 'expected_amount' => 20000, 'status' => 'ready']);
        Contribution::create(['cycle_id' => $baseCycle->id, 'membership_id' => $borrower->id, 'amount' => 20000, 'due_date' => today(), 'status' => 'paid', 'paid_at' => now()]);
        try {
            $svc->request($borrower, 10, 6, 'Trop petit montant', [$g->id]);
            $this->fail('Le montant minimum aurait dû être refusé.');
        } catch (\DomainException) {
        }
        $loan = $svc->request($borrower, 60000, 6, 'Achat de matériel', [$g->id]);
        try {
            $svc->approve($loan, $manager);
            $this->fail('Les garants doivent accepter avant.');
        } catch (\DomainException) {
        }
        $svc->guarantee($loan, $g, true);
        try {
            $svc->approve($loan, $borrower);
            $this->fail('On ne peut pas approuver son propre prêt.');
        } catch (\DomainException) {
        }
        $svc->approve($loan, $manager);
        $svc->disburse($loan);
        $loan->refresh();
        $this->assertSame('disbursed', $loan->status);
        $this->assertCount(6, $loan->installments);
        $this->assertSame(60000, $ledger->accountOf($borrower)->balance);
        $ledger->credit($ledger->accountOf($borrower), $loan->total_due, LedgerType::Deposit);
        $svc->repay($loan, $loan->total_due, $borrower);
        $this->assertSame('repaid', $loan->refresh()->status);
    }

    public function test_member_cannot_approve_and_non_members_are_rejected_by_the_api(): void
    {
        $manager = $this->member(null, 'treasurer');
        $plain = $this->member();
        $borrower = $this->member();
        $loan = Loan::create(['cooperative_id' => $this->coop->id, 'membership_id' => $borrower->id, 'amount' => 50000, 'interest_rate' => 12, 'term_months' => 6, 'purpose' => 'Test', 'status' => 'requested']);
        LoanGuarantor::create(['loan_id' => $loan->id, 'membership_id' => $plain->id, 'status' => 'accepted']);
        $this->getJson("/api/v1/coops/{$this->coop->id}/loans")->assertUnauthorized();
        $this->actingAs($plain->user)->postJson("/api/v1/coops/{$this->coop->id}/loans/{$loan->id}/approve")->assertForbidden();
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->getJson("/api/v1/coops/{$this->coop->id}/dashboard")->assertForbidden();
        $this->actingAs($manager->user)->postJson("/api/v1/coops/{$this->coop->id}/loans/{$loan->id}/approve")->assertOk();
        $other = $this->member();
        $this->actingAs($other->user)->getJson("/api/v1/coops/{$this->coop->id}/members/{$borrower->id}")->assertOk()->assertJsonMissingPath('loans');
    }

    public function test_scheduled_commands_run(): void
    {
        [$t] = $this->tontine(3);
        $svc = app(TontineService::class);
        $svc->draw($t);
        $svc->activate($t);
        $this->artisan('contributions:flag-late')->assertSuccessful();
        $this->artisan('loans:flag-late')->assertSuccessful();
        $this->assertTrue(Contribution::where('status', 'late')->exists());
    }
}
