<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Couleurs et logo de DocumentPersonnalisation, partagés avec les documents émis par Datensis.
 */
final class CharteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('couleur_principale', ColorType::class, [
                'label' => 'Couleur principale',
                'help' => 'En-tête du portail et des documents Datensis.',
                'constraints' => [new Assert\NotBlank()],
                'attr' => ['data-theme-preview-target' => 'primary', 'data-action' => 'input->theme-preview#update'],
            ])
            ->add('couleur_secondaire', ColorType::class, [
                'label' => 'Couleur secondaire',
                'help' => 'Second ton du dégradé, badges et boutons secondaires.',
                'constraints' => [new Assert\NotBlank()],
                'attr' => ['data-theme-preview-target' => 'secondary', 'data-action' => 'input->theme-preview#update'],
            ])
            ->add('couleur_accent', ColorType::class, [
                'label' => 'Couleur d\'accent',
                'help' => 'Alertes, focus et troisième ton de l\'interface.',
                'constraints' => [new Assert\NotBlank()],
                'attr' => ['data-theme-preview-target' => 'accent', 'data-action' => 'input->theme-preview#update'],
            ])
            ->add('couleur_fond', ColorType::class, [
                'label' => 'Couleur de fond',
                'help' => 'Fond de page lorsque le thème original est choisi.',
                'constraints' => [new Assert\NotBlank()],
                'attr' => ['data-theme-preview-target' => 'background', 'data-action' => 'input->theme-preview#update'],
            ])
            ->add('couleur_navbar', ColorType::class, [
                'label' => 'Couleur des barres',
                'help' => 'Barre du haut et menu latéral lorsque le thème original est choisi.',
                'constraints' => [new Assert\NotBlank()],
                'attr' => ['data-theme-preview-target' => 'navbar', 'data-action' => 'input->theme-preview#update'],
            ])
            ->add('couleur_carte', ColorType::class, [
                'label' => 'Couleur des cartes',
                'help' => 'Cartes, encarts et bloc société. Le texte s\'adapte pour rester lisible. Le thème sombre garde des cartes foncées.',
                'constraints' => [new Assert\NotBlank()],
                'attr' => ['data-theme-preview-target' => 'card', 'data-action' => 'input->theme-preview#update'],
            ])
            ->add('logo_fichier', FileType::class, [
                'label' => 'Importer un logo',
                'required' => false,
                'help' => 'PNG, JPEG, WEBP ou GIF, 2 Mo maximum. Le fichier importé est utilisé pour cette société.',
                'constraints' => [
                    new Assert\File(maxSize: '2M'),
                    new Assert\Callback(static function (mixed $file, ExecutionContextInterface $context): void {
                        if (null === $file || '' === $file) {
                            return;
                        }
                        if (!$file instanceof UploadedFile || null === LogoImage::extension($file)) {
                            $context->buildViolation(LogoImage::MESSAGE)->addViolation();
                        }
                    }),
                ],
                'attr' => [
                    'accept' => 'image/png,image/jpeg,image/webp,image/gif',
                    'data-theme-preview-target' => 'file',
                    'data-action' => 'change->theme-preview#previewFile',
                ],
            ])
            ->add('logo_url', UrlType::class, [
                'label' => 'Ou une URL de logo',
                'required' => false,
                'default_protocol' => 'https',
                'help' => 'Utilisée seulement s\'il n\'y a pas de fichier importé.',
                'constraints' => [new Assert\Url(requireTld: true), new Assert\Length(max: 200)],
                'attr' => ['placeholder' => 'https://…', 'data-theme-preview-target' => 'logo', 'data-action' => 'input->theme-preview#update'],
            ]);
    }
}
