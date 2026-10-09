<?php

namespace App\Controller;

use App\Datensis\BrandingRepository;
use App\Datensis\ClientContext;
use App\Datensis\InvoiceRepository;
use App\Datensis\InvoiceStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/facturation')]
final class FacturationController extends AbstractController
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly ClientContext $clientContext,
    ) {
    }

    #[Route('', name: 'app_facturation', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filters = [
            'q' => trim($request->query->getString('q')),
            'statut' => $request->query->getString('statut'),
        ];

        return $this->render('facturation/index.html.twig', [
            'available' => $this->invoiceRepository->isAvailable(),
            'invoices' => $this->invoiceRepository->findByClient($this->clientContext->getClientId(), $filters),
            'statuses' => InvoiceStatus::cases(),
            'filters' => $filters,
        ]);
    }

    #[Route('/{id}', name: 'app_facture_show', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function show(int $id, BrandingRepository $brandingRepository): Response
    {
        $invoice = $this->invoiceRepository->find($this->clientContext->getClientId(), $id)
            ?? throw $this->createNotFoundException('Facture introuvable pour cette société.');

        return $this->render('facturation/show.html.twig', [
            'invoice' => $invoice,
            'branding' => $brandingRepository->forClient($this->clientContext->getClientId(), $this->clientContext->getClientName()),
        ]);
    }
}
