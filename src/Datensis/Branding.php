<?php

namespace App\Datensis;

/**
 * Charte graphique d'une société (myfacturation_documentpersonnalisation).
 */
final class Branding
{
    public const DEFAULT_PRIMARY = '#00778B';
    public const DEFAULT_SECONDARY = '#82C4A8';
    public const DEFAULT_ACCENT = '#E96148';
    public const DEFAULT_BACKGROUND = '#FFFFFF';
    public const DEFAULT_NAVBAR = '#ffffff';
    public const DEFAULT_CARD = '#ffffff';

    /**
     * Palettes proposées dans le formulaire. Le fond et les barres servent au thème original.
     *
     * @var list<array{name: string, primary: string, secondary: string, accent: string, background: string, navbar: string, card: string}>
     */
    public const PALETTES = [
        ['name' => 'Datensis', 'primary' => '#00778B', 'secondary' => '#82C4A8', 'accent' => '#E96148', 'background' => '#FFFFFF', 'navbar' => '#FFFFFF', 'card' => '#FFFFFF'],
        ['name' => 'Pétrole', 'primary' => '#005f73', 'secondary' => '#94d2bd', 'accent' => '#ee6c4d', 'background' => '#f0fdfa', 'navbar' => '#005f73', 'card' => '#ffffff'],
        ['name' => 'Bleu et or', 'primary' => '#1e3a8a', 'secondary' => '#f59e0b', 'accent' => '#38bdf8', 'background' => '#f8fafc', 'navbar' => '#1e3a8a', 'card' => '#ffffff'],
        ['name' => 'Émeraude', 'primary' => '#047857', 'secondary' => '#6ee7b7', 'accent' => '#f59e0b', 'background' => '#ecfdf5', 'navbar' => '#047857', 'card' => '#ffffff'],
        ['name' => 'Corail', 'primary' => '#c2410c', 'secondary' => '#fdba74', 'accent' => '#0f766e', 'background' => '#fff7ed', 'navbar' => '#c2410c', 'card' => '#ffffff'],
        ['name' => 'Bordeaux', 'primary' => '#9f1239', 'secondary' => '#fb7185', 'accent' => '#fbbf24', 'background' => '#fff1f2', 'navbar' => '#9f1239', 'card' => '#ffffff'],
        ['name' => 'Ardoise', 'primary' => '#334155', 'secondary' => '#94a3b8', 'accent' => '#38bdf8', 'background' => '#f8fafc', 'navbar' => '#334155', 'card' => '#ffffff'],
    ];

    public function __construct(
        public readonly string $primaryColor = self::DEFAULT_PRIMARY,
        public readonly string $secondaryColor = self::DEFAULT_SECONDARY,
        public readonly string $accentColor = self::DEFAULT_ACCENT,
        public readonly string $backgroundColor = self::DEFAULT_BACKGROUND,
        public readonly string $navbarColor = self::DEFAULT_NAVBAR,
        public readonly string $cardColor = self::DEFAULT_CARD,
        public readonly ?string $logoUrl = null,
        public readonly ?string $externalLogoUrl = null,
        public readonly string $companyName = '',
        public readonly string $address = '',
        public readonly string $phone = '',
        public readonly string $email = '',
        public readonly string $siret = '',
        public readonly string $vatNumber = '',
        public readonly bool $persisted = false,
    ) {
    }

    /**
     * Couleur de texte lisible sur la couleur principale.
     */
    public function getTextOnPrimary(): string
    {
        return self::isLight($this->primaryColor) ? '#0f172a' : '#ffffff';
    }

    public function getTextOnSecondary(): string
    {
        return self::isLight($this->secondaryColor) ? '#0f172a' : '#ffffff';
    }

    public function getTextOnAccent(): string
    {
        return self::isLight($this->accentColor) ? '#0f172a' : '#ffffff';
    }

    public function getTextOnNavbar(): string
    {
        return self::isLight($this->navbarColor) ? '#0f172a' : '#ffffff';
    }

    public function getTextOnBackground(): string
    {
        return self::isLight($this->backgroundColor) ? '#0f172a' : '#ffffff';
    }

    public function getTextOnCard(): string
    {
        return self::isLight($this->cardColor) ? '#0f172a' : '#ffffff';
    }

    public function getCssVariables(): string
    {
        return \sprintf(
            '--ml2c-primary: %1$s; --ml2c-secondary: %2$s; --ml2c-tertiary: %3$s; --ml2c-background: %4$s; --ml2c-navbar: %5$s; --ml2c-card: %6$s; --ml2c-on-primary: %7$s; --ml2c-on-secondary: %8$s; --ml2c-on-accent: %9$s; --ml2c-on-navbar: %10$s; --ml2c-on-background: %11$s; --ml2c-on-card: %12$s; --color-primary: %1$s; --color-secondary: %2$s; --color-accent: %3$s; --color-on-primary: %7$s; --color-on-secondary: %8$s; --ml2c-gradient-primary: linear-gradient(135deg, %1$s 0%%, %2$s 100%%); --ml2c-gradient-secondary: linear-gradient(135deg, %2$s 0%%, %1$s 100%%); --ml2c-gradient-warm: linear-gradient(135deg, %3$s 0%%, %1$s 100%%);',
            $this->primaryColor,
            $this->secondaryColor,
            $this->accentColor,
            $this->backgroundColor,
            $this->navbarColor,
            $this->cardColor,
            $this->getTextOnPrimary(),
            $this->getTextOnSecondary(),
            $this->getTextOnAccent(),
            $this->getTextOnNavbar(),
            $this->getTextOnBackground(),
            $this->getTextOnCard(),
        );
    }

    public static function normalizeColor(?string $color, string $default): string
    {
        $color = strtolower(trim((string) $color));

        return preg_match('/^#[0-9a-f]{6}$/', $color) ? $color : $default;
    }

    private static function isLight(string $hex): bool
    {
        $channels = array_map(static function (string $part): float {
            $channel = hexdec($part) / 255;

            return $channel <= 0.03928
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($hex, 1), 2));

        $luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];

        return $luminance > 0.179;
    }
}
