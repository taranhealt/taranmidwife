<?php
namespace App\Enums;
enum UserRole: string {
    case Patient = 'patient';
    case Partner = 'partner';
    case Admin = 'admin';
    case Superadmin = 'superadmin';
}
