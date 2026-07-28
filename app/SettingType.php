<?php

namespace App;

enum SettingType: string
{
    case String = 'string';
    case Text = 'text';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Date = 'date';
    case Time = 'time';
    case Json = 'json';
    case Image = 'image';
    case File = 'file';
    case Secret = 'secret';
}
