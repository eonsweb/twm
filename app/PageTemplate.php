<?php

namespace App;

enum PageTemplate: string
{
    case Default = 'default';
    case Homepage = 'homepage';
    case FullWidth = 'full-width';
    case Sidebar = 'sidebar';
    case LandingPage = 'landing-page';
    case ModuleIndex = 'module-index';
    case Contact = 'contact';
    case Legal = 'legal';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
