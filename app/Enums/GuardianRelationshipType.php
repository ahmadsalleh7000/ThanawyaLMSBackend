<?php

namespace App\Enums;

enum GuardianRelationshipType: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Guardian = 'guardian';
    case Other = 'other';
}
