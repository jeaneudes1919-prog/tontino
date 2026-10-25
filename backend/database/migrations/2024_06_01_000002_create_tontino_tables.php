<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooperatives', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->string('city')->nullable();
            $t->string('invite_code')->unique();
            $t->string('currency')->default('XOF');
            $t->json('settings');
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });
        Schema::create('memberships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cooperative_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role')->default('member');
            $t->string('status')->default('active');
            $t->string('member_number');
            $t->timestamp('joined_at')->nullable();
            $t->timestamps();
            $t->unique(['cooperative_id', 'user_id']);
            $t->unique(['cooperative_id', 'member_number']);
        });
        Schema::create('accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cooperative_id')->constrained()->cascadeOnDelete();
            $t->string('type');
            $t->unsignedBigInteger('owner_id')->default(0);
            $t->bigInteger('balance')->default(0);
            $t->timestamps();
            $t->unique(['cooperative_id', 'type', 'owner_id']);
        });
        Schema::create('ledger_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->string('direction');
            $t->bigInteger('amount');
            $t->bigInteger('balance_after');
            $t->string('type');
            $t->string('reference_type')->nullable();
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->string('description')->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['account_id', 'created_at']);
        });
        Schema::create('tontines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cooperative_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->text('description')->nullable();
            $t->string('type')->default('rotative');
            $t->unsignedBigInteger('contribution_amount');
            $t->string('frequency')->default('weekly');
            $t->date('start_date');
            $t->unsignedInteger('max_members')->default(12);
            $t->unsignedInteger('penalty_percent')->default(5);
            $t->string('status')->default('draft');
            $t->timestamp('drawn_at')->nullable();
            $t->string('draw_seed')->nullable();
            $t->timestamps();
        });
        Schema::create('tontine_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tontine_id')->constrained()->cascadeOnDelete();
            $t->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('position')->nullable();
            $t->timestamps();
            $t->unique(['tontine_id', 'membership_id']);
        });
        Schema::create('cycles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tontine_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('number');
            $t->date('due_date');
            $t->foreignId('beneficiary_membership_id')->nullable()->constrained('memberships')->nullOnDelete();
            $t->unsignedBigInteger('expected_amount');
            $t->unsignedBigInteger('collected_amount')->default(0);
            $t->string('status')->default('upcoming');
            $t->timestamp('paid_out_at')->nullable();
            $t->timestamps();
            $t->unique(['tontine_id', 'number']);
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cooperative_id')->constrained()->cascadeOnDelete();
            $t->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $t->string('purpose');
            $t->string('provider');
            $t->string('phone');
            $t->unsignedBigInteger('amount');
            $t->string('status')->default('pending');
            $t->string('reference')->unique();
            $t->string('related_type')->nullable();
            $t->unsignedBigInteger('related_id')->nullable();
            $t->string('failure_reason')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('contributions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cycle_id')->constrained()->cascadeOnDelete();
            $t->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('amount');
            $t->unsignedBigInteger('penalty_amount')->default(0);
            $t->date('due_date');
            $t->string('status')->default('pending');
            $t->timestamp('paid_at')->nullable();
            $t->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->unique(['cycle_id', 'membership_id']);
        });
        Schema::create('loans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cooperative_id')->constrained()->cascadeOnDelete();
            $t->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('amount');
            $t->decimal('interest_rate', 5, 2);
            $t->unsignedInteger('term_months');
            $t->string('purpose');
            $t->string('status')->default('requested');
            $t->foreignId('reviewed_by')->nullable()->constrained('memberships')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('rejection_reason')->nullable();
            $t->timestamp('disbursed_at')->nullable();
            $t->unsignedBigInteger('total_interest')->default(0);
            $t->unsignedBigInteger('total_due')->default(0);
            $t->timestamps();
        });
        Schema::create('loan_guarantors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $t->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $t->string('status')->default('pending');
            $t->timestamps();
            $t->unique(['loan_id', 'membership_id']);
        });
        Schema::create('loan_installments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('number');
            $t->date('due_date');
            $t->unsignedBigInteger('principal');
            $t->unsignedBigInteger('interest');
            $t->unsignedBigInteger('paid_amount')->default(0);
            $t->string('status')->default('pending');
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });
        Schema::create('announcements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cooperative_id')->constrained()->cascadeOnDelete();
            $t->foreignId('author_id')->constrained('users');
            $t->string('title');
            $t->text('body');
            $t->boolean('pinned')->default(false);
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });
        Schema::create('meetings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cooperative_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->dateTime('starts_at');
            $t->string('location')->nullable();
            $t->text('agenda')->nullable();
            $t->text('minutes')->nullable();
            $t->string('status')->default('scheduled');
            $t->timestamps();
        });
        Schema::create('meeting_attendances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $t->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $t->string('status')->nullable();
            $t->unique(['meeting_id', 'membership_id']);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('cooperative_id')->nullable()->index();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('action');
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('old')->nullable();
            $t->json('new')->nullable();
            $t->string('ip')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'meeting_attendances', 'meetings', 'announcements', 'loan_installments', 'loan_guarantors', 'loans', 'contributions', 'payments', 'cycles', 'tontine_members', 'tontines', 'ledger_entries', 'accounts', 'memberships', 'cooperatives'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
