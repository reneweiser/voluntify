<?php

namespace App\Support;

class CustomFieldTemplates
{
    /**
     * @return array<string, array{label: string, type: string, options: array<string, mixed>, required: bool}>
     */
    public static function all(): array
    {
        return [
            'emergency_contact' => [
                'label' => 'Emergency Contact',
                'type' => 'text',
                'options' => ['multiline' => true],
                'required' => true,
            ],
            'dietary_restrictions' => [
                'label' => 'Dietary Restrictions',
                'type' => 'select',
                'options' => ['choices' => ['None', 'Vegetarian', 'Vegan', 'Gluten-free', 'Other']],
                'required' => false,
            ],
            'tshirt_size' => [
                'label' => 'T-Shirt Size',
                'type' => 'select',
                'options' => ['choices' => ['XS', 'S', 'M', 'L', 'XL', 'XXL']],
                'required' => false,
            ],
            'first_aid_certificate' => [
                'label' => 'Has First Aid Certificate',
                'type' => 'checkbox',
                'options' => [],
                'required' => false,
            ],
            'previous_experience' => [
                'label' => 'Previous Volunteer Experience',
                'type' => 'text',
                'options' => ['multiline' => true],
                'required' => false,
            ],
            'photo_release' => [
                'label' => 'Photo/Video Release Agreement',
                'type' => 'checkbox',
                'options' => [],
                'required' => true,
            ],
        ];
    }
}
