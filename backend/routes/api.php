<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\ContributionController;
use App\Http\Controllers\CooperativeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MiscController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TontineController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::patch('/me', [AuthController::class, 'update']);
        Route::get('/my/cooperatives', [CooperativeController::class, 'mine']);
        Route::post('/cooperatives', [CooperativeController::class, 'store']);
        Route::post('/cooperatives/join', [CooperativeController::class, 'join']);

        Route::prefix('coops/{coop}')->middleware('coop')->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'index']);

            Route::get('/members', [MemberController::class, 'index']);
            Route::get('/members/{membership}', [MemberController::class, 'show']);
            Route::post('/members', [MemberController::class, 'store'])->middleware('coop.role:admin');
            Route::patch('/members/{membership}', [MemberController::class, 'update'])->middleware('coop.role:admin');

            Route::get('/wallet', [WalletController::class, 'show']);
            Route::get('/wallet/entries', [WalletController::class, 'entries']);
            Route::post('/wallet/deposit', [WalletController::class, 'deposit']);
            Route::post('/wallet/withdraw', [WalletController::class, 'withdraw']);
            Route::get('/payments', [PaymentController::class, 'index']);
            Route::get('/payments/{reference}', [PaymentController::class, 'show']);

            Route::get('/tontines', [TontineController::class, 'index']);
            Route::get('/tontines/{tontine}', [TontineController::class, 'show']);
            Route::get('/cycles/{cycle}', [TontineController::class, 'cycle']);
            Route::middleware('coop.role:admin,treasurer')->group(function () {
                Route::post('/tontines', [TontineController::class, 'store']);
                Route::patch('/tontines/{tontine}', [TontineController::class, 'update']);
                Route::post('/tontines/{tontine}/members', [TontineController::class, 'addMembers']);
                Route::delete('/tontines/{tontine}/members/{membershipId}', [TontineController::class, 'removeMember']);
                Route::post('/tontines/{tontine}/draw', [TontineController::class, 'draw']);
                Route::post('/tontines/{tontine}/activate', [TontineController::class, 'activate']);
                Route::post('/cycles/{cycle}/payout', [TontineController::class, 'payout']);
                Route::post('/contributions/{contribution}/waive', [ContributionController::class, 'waive']);
            });

            Route::get('/contributions', [ContributionController::class, 'index']);
            Route::post('/contributions/{contribution}/pay', [ContributionController::class, 'pay']);
            Route::post('/contributions/{contribution}/pay-from-wallet', [ContributionController::class, 'payFromWallet']);

            Route::get('/loans', [LoanController::class, 'index']);
            Route::post('/loans/simulate', [LoanController::class, 'simulate']);
            Route::post('/loans', [LoanController::class, 'store']);
            Route::get('/loans/{loan}', [LoanController::class, 'show']);
            Route::post('/loans/{loan}/guarantee', [LoanController::class, 'guarantee']);
            Route::post('/loans/{loan}/repay', [LoanController::class, 'repay']);
            Route::middleware('coop.role:admin,treasurer')->group(function () {
                Route::post('/loans/{loan}/approve', [LoanController::class, 'approve']);
                Route::post('/loans/{loan}/reject', [LoanController::class, 'reject']);
                Route::post('/loans/{loan}/disburse', [LoanController::class, 'disburse']);
            });

            Route::get('/announcements', [CommunityController::class, 'announcements']);
            Route::get('/meetings', [CommunityController::class, 'meetings']);
            Route::middleware('coop.role:admin,treasurer')->group(function () {
                Route::post('/announcements', [CommunityController::class, 'saveAnnouncement']);
                Route::put('/announcements/{announcement}', [CommunityController::class, 'saveAnnouncement']);
                Route::delete('/announcements/{announcement}', [CommunityController::class, 'deleteAnnouncement']);
                Route::post('/meetings', [CommunityController::class, 'saveMeeting']);
                Route::put('/meetings/{meeting}', [CommunityController::class, 'saveMeeting']);
                Route::delete('/meetings/{meeting}', [CommunityController::class, 'deleteMeeting']);
                Route::put('/meetings/{meeting}/attendance', [CommunityController::class, 'attendance']);
                Route::get('/reports/cooperative-summary', [ReportController::class, 'summary']);
                Route::get('/reports/contributions.csv', [ReportController::class, 'contributionsCsv']);
                Route::get('/reports/ledger.csv', [ReportController::class, 'ledgerCsv']);
            });
            Route::get('/reports/member-statement/{membership}', [ReportController::class, 'statement']);

            Route::get('/audit-logs', [MiscController::class, 'audit'])->middleware('coop.role:admin');
            Route::get('/settings', [MiscController::class, 'settings']);
            Route::patch('/settings', [MiscController::class, 'updateSettings'])->middleware('coop.role:admin');
            Route::get('/notifications', [MiscController::class, 'notifications']);
            Route::post('/notifications/read-all', [MiscController::class, 'readAll']);
            Route::patch('/notifications/{id}/read', [MiscController::class, 'read']);
        });
    });
});
