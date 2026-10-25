<?php

namespace App\Enums;

enum LedgerType: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    case Contribution = 'contribution';
    case Payout = 'payout';
    case LoanDisbursement = 'loan_disbursement';
    case LoanRepayment = 'loan_repayment';
    case Interest = 'interest';
    case Penalty = 'penalty';
    case Adjustment = 'adjustment';
}
