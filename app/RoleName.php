<?php

namespace App;

enum RoleName: string
{
    case SuperAdmin = 'Super Admin';
    case Administrator = 'Administrator';
    case Editor = 'Editor';
    case MediaManager = 'Media Manager';
    case FinanceOfficer = 'Finance Officer';
}
