<?php
namespace App\Enums;
enum UserRole: string {
    case Patient = 'patient';
    case Partner = 'partner';
    case OpsAdmin = 'ops_admin';
    case Reviewer = 'reviewer';
    case SysAdmin = 'sysadmin';
}
