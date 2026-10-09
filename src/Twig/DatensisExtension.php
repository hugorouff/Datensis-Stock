<?php

namespace App\Twig;

use App\Datensis\Branding;
use App\Datensis\BrandingRepository;
use App\Datensis\ClientContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class DatensisExtension extends AbstractExtension
{
    public function __construct(
        private readonly ClientContext $clientContext,
        private readonly BrandingRepository $brandingRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('current_branding', $this->currentBranding(...)),
            new TwigFunction('current_client_id', fn () => $this->clientContext->hasClient() ? $this->clientContext->getClientId() : null),
            new TwigFunction('current_client_name', fn () => $this->clientContext->hasClient() ? $this->clientContext->getClientName() : ''),
        ];
    }

    /**
     * Charte de la société courante, ou la charte par défaut de Datensis hors connexion.
     */
    public function currentBranding(): Branding
    {
        if (!$this->clientContext->hasClient()) {
            return new Branding();
        }

        return $this->brandingRepository->forClient($this->clientContext->getClientId(), $this->clientContext->getClientName());
    }
}
