<?php
namespace App\Enums;
enum PartnerVerificationStatus: string {
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case NeedsCorrection = 'NEEDS_CORRECTION';
    case InReview = 'IN_REVIEW';
    case Approved = 'APPROVED';
    case Active = 'ACTIVE';
    case Rejected = 'REJECTED';
    case Expired = 'EXPIRED';
    case Suspended = 'SUSPENDED';
}
