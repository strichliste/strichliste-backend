<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CreateTransactionType extends AbstractType {

    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $builder
            ->add('direction', HiddenType::class, [
                'constraints' => [new Assert\Choice(choices: ['deposit', 'dispense'])],
            ])
            // MoneyType displays/parses major units (€) but the bound value is a float
            // representing major units. The controller multiplies by 100 to get cents.
            ->add('amount', MoneyType::class, [
                'label' => 'transactions.amount_label',
                'currency' => false,
                'scale' => 2,
                'constraints' => [new Assert\NotBlank(), new Assert\NotEqualTo(value: 0)],
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'transactions.comment_label',
                'required' => false,
                'constraints' => [new Assert\Length(max: 255)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void {
        $resolver
            ->setDefaults(['data_class' => null])
            // user_id is REQUIRED, not optional. The CSRF token id is scoped by
            // it; defaulting to null silently collapses scope to a single
            // global token, which would re-open the cross-user-replay window
            // we explicitly closed earlier.
            ->setRequired('user_id')
            ->setAllowedTypes('user_id', 'int');

        $resolver->setDefault('csrf_token_id', function (\Symfony\Component\OptionsResolver\Options $opts) {
            return 'create_transaction' . $opts['user_id'];
        });
    }
}
