<?php

namespace App\Form;

use App\Datensis\ArticleRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Champs modifiables d'un article Datensis (myfacturation_article).
 */
final class ArticleType extends AbstractType
{
    public const VAT_RATES = ['20 %' => '20.00', '10 %' => '10.00', '5,5 %' => '5.50', '2,1 %' => '2.10', '0 %' => '0.00'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code_article', TextType::class, [
                'label' => 'Code article',
                'required' => false,
                'help' => 'Laisser vide pour générer un code ART… automatiquement.',
                'constraints' => [new Assert\Length(max: 30)],
            ])
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 1000)],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('categorie_id', ChoiceType::class, [
                'label' => 'Catégorie',
                'required' => false,
                'placeholder' => 'Sans catégorie',
                'choices' => array_flip($options['categories']),
            ])
            ->add('type_article', ChoiceType::class, [
                'label' => "Type d'article",
                'choices' => array_flip(ArticleRepository::TYPES),
            ])
            ->add('unite', ChoiceType::class, [
                'label' => 'Unité',
                'required' => false,
                'placeholder' => 'Non définie',
                'choices' => array_flip(ArticleRepository::UNITS),
            ])
            ->add('prix_ht', NumberType::class, [
                'label' => 'Prix de vente HT (€)',
                'help' => 'Enregistré avec 6 décimales, comme dans Datensis.',
                'input' => 'string',
                'scale' => 6,
                'html5' => true,
                'attr' => ['step' => '0.000001', 'min' => 0],
                'constraints' => [new Assert\NotBlank(), new Assert\PositiveOrZero()],
            ])
            ->add('prix_achat', NumberType::class, [
                'label' => "Prix d'achat HT (€)",
                'required' => false,
                'input' => 'string',
                'scale' => 2,
                'html5' => true,
                'attr' => ['step' => '0.01', 'min' => 0],
                'constraints' => [new Assert\PositiveOrZero()],
            ])
            ->add('taux_tva', ChoiceType::class, [
                'label' => 'Taux de TVA',
                'required' => false,
                'placeholder' => 'Non défini',
                'choices' => $options['vat_rates'],
            ])
            ->add('suivi_stock', CheckboxType::class, [
                'label' => 'Activer le suivi de stock',
                'required' => false,
            ])
            ->add('stock_minimum', NumberType::class, [
                'label' => 'Stock minimum (alerte)',
                'required' => false,
                'input' => 'string',
                'scale' => 3,
                'html5' => true,
                'attr' => ['step' => '0.001', 'min' => 0],
                'constraints' => [new Assert\PositiveOrZero()],
            ])
            ->add('stock_critique', NumberType::class, [
                'label' => 'Stock critique (alerte urgente)',
                'required' => false,
                'input' => 'string',
                'scale' => 3,
                'html5' => true,
                'attr' => ['step' => '0.001', 'min' => 0],
                'constraints' => [new Assert\PositiveOrZero()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'categories' => [],
            'vat_rates' => self::VAT_RATES,
        ]);
        $resolver->setAllowedTypes('categories', 'array');
        $resolver->setAllowedTypes('vat_rates', 'array');
    }
}
