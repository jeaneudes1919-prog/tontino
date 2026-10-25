<?php

namespace App\Services;

use App\Enums\LedgerType;
use App\Exceptions\InsufficientFundsException;
use App\Models\Account;
use App\Models\Cooperative;
use App\Models\Membership;
use App\Models\Tontine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    public function accountOf(Membership $m): Account
    {
        return Account::firstOrCreate(
            ['cooperative_id' => $m->cooperative_id, 'type' => 'member', 'owner_id' => $m->id],
            ['balance' => 0],
        );
    }

    public function fundOf(Cooperative $c): Account
    {
        return Account::firstOrCreate(
            ['cooperative_id' => $c->id, 'type' => 'cooperative_fund', 'owner_id' => 0],
            ['balance' => 0],
        );
    }

    public function potOf(Tontine $t): Account
    {
        return Account::firstOrCreate(
            ['cooperative_id' => $t->cooperative_id, 'type' => 'tontine_pot', 'owner_id' => $t->id],
            ['balance' => 0],
        );
    }

    /** Transfert atomique entre deux comptes : deux écritures (débit et crédit) avec le solde après opération. */
    public function transfer(Account $from, Account $to, int $amount, LedgerType $type, array $meta = [], ?Model $ref = null, ?string $desc = null): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Le montant doit être positif.');
        }
        DB::transaction(function () use ($from, $to, $amount, $type, $meta, $ref, $desc) {
            $ids = [$from->id, $to->id];
            sort($ids);
            $locked = Account::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $f = $locked[$from->id];
            $t = $locked[$to->id];
            if ($f->type === 'member' && $f->balance < $amount) {
                throw new InsufficientFundsException('Solde insuffisant.');
            }
            $f->balance -= $amount;
            $t->balance += $amount;
            $f->save();
            $t->save();
            $this->entry($f, 'debit', $amount, $type, $meta, $ref, $desc);
            $this->entry($t, 'credit', $amount, $type, $meta, $ref, $desc);
        });
        $from->refresh();
        $to->refresh();
    }

    /** Entrée d'argent externe (dépôt Mobile Money, ajustement). */
    public function credit(Account $a, int $amount, LedgerType $type, array $meta = [], ?Model $ref = null, ?string $desc = null): void
    {
        DB::transaction(function () use ($a, $amount, $type, $meta, $ref, $desc) {
            $l = Account::lockForUpdate()->findOrFail($a->id);
            $l->balance += $amount;
            $l->save();
            $this->entry($l, 'credit', $amount, $type, $meta, $ref, $desc);
        });
        $a->refresh();
    }

    /** Sortie d'argent externe (retrait). */
    public function debit(Account $a, int $amount, LedgerType $type, array $meta = [], ?Model $ref = null, ?string $desc = null): void
    {
        DB::transaction(function () use ($a, $amount, $type, $meta, $ref, $desc) {
            $l = Account::lockForUpdate()->findOrFail($a->id);
            if ($l->balance < $amount) {
                throw new InsufficientFundsException('Solde insuffisant.');
            }
            $l->balance -= $amount;
            $l->save();
            $this->entry($l, 'debit', $amount, $type, $meta, $ref, $desc);
        });
        $a->refresh();
    }

    private function entry(Account $a, string $dir, int $amount, LedgerType $type, array $meta, ?Model $ref, ?string $desc): void
    {
        $a->entries()->create([
            'direction' => $dir, 'amount' => $amount, 'balance_after' => $a->balance, 'type' => $type->value,
            'reference_type' => $ref ? class_basename($ref) : null, 'reference_id' => $ref?->getKey(),
            'description' => $desc, 'meta' => $meta ?: null,
        ]);
    }
}
