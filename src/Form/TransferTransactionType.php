<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class TransferTransactionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $excludeUser = $options['exclude_user'];
        $builder
            ->add('recipient', EntityType::class, [
                'label' => 'transactions.transfer.recipient_label',
                'class' => User::class,
                'choice_label' => 'name',
                'placeholder' => 'transactions.transfer.recipient_placeholder',
                'query_builder' => function (\App\Repository\UserRepository $repo) use ($excludeUser) {
                    $qb = $repo->createQueryBuilder('u')
                        ->where('u.disabled = false')
                        ->orderBy('u.name');
                    if ($excludeUser) {
                        $qb->andWhere('u.id <> :excluded')
                            ->setParameter('excluded', $excludeUser->getId());
                    }

                    return $qb;
                },
                'constraints' => [new Assert\NotBlank()],
            ])
            // Major units; controller multiplies by 100 to get cents.
            ->add('amount', MoneyType::class, [
                'label' => 'transactions.amount_label',
                'currency' => false,
                'scale' => 2,
                'constraints' => [new Assert\NotBlank(), new Assert\Positive(), new Assert\LessThanOrEqual(1000000)],
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
            ->setDefaults(['data_class' => null, 'exclude_user' => null])
            ->setAllowedTypes('exclude_user', [User::class, 'null']);
    }
}
