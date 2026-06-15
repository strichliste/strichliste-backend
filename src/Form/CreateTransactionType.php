<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class CreateTransactionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('direction', HiddenType::class, [
                'constraints' => [new Assert\Choice(choices: ['deposit', 'dispense'])],
            ])
            // MoneyType binds major units (€); the controller converts to cents
            ->add('amount', MoneyType::class, [
                'label' => 'transactions.amount_label',
                'currency' => false,
                'scale' => 2,
                'constraints' => [new Assert\NotBlank(), new Assert\NotEqualTo(value: 0), new Assert\Range(min: -1000000, max: 1000000)],
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'transactions.comment_label',
                'required' => false,
                'constraints' => [new Assert\Length(max: 255)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults(['data_class' => null])
            // required on purpose: the CSRF token id is scoped by user_id, a default would collapse it to one global token
            ->setRequired('user_id')
            ->setAllowedTypes('user_id', 'int');

        $resolver->setDefault('csrf_token_id', fn (\Symfony\Component\OptionsResolver\Options $opts) => 'create_transaction'.$opts['user_id']);
    }
}
