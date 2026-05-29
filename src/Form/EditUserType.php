<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class EditUserType extends AbstractType {

    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $builder
            ->add('name', TextType::class, [
                'label' => 'user.edit.name_label',
                'attr' => [
                    'maxlength' => 64,
                    'minlength' => 1,
                ],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 1, max: 64),
                    new Assert\Regex(pattern: '/[\x00-\x1F\x7F]/u', match: false, message: 'user.create.errors.invalid_name'),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'user.edit.email_label',
                'required' => false,
                'attr' => ['maxlength' => 255],
                'constraints' => [
                    new Assert\Length(max: 255),
                    new Assert\Email(mode: 'html5'),
                ],
            ])
            ->add('isDisabled', CheckboxType::class, [
                'label' => 'user.edit.disable_label',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
