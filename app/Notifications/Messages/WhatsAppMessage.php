<?php

namespace App\Notifications\Messages;

class WhatsAppMessage
{
    public function __construct(
        public string $template,
        public array $variables = [],
        public ?string $locale = null,
    ) {}

    public static function create(string $template, array $variables = [], ?string $locale = null): static
    {
        return new static($template, $variables, $locale);
    }

    public function template(string $template): static
    {
        $this->template = $template;

        return $this;
    }

    public function variables(array $variables): static
    {
        $this->variables = $variables;

        return $this;
    }

    public function locale(?string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }
}
