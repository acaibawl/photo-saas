<?php

namespace App\Domain\Child;

enum ChildStatus: string
{
    case Enrolled = 'enrolled';
    case Graduated = 'graduated';
    case Withdrawn = 'withdrawn';
}
