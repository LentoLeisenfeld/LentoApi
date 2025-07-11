<?php

namespace Lento\Validation;

use ReflectionClass;
use ReflectionProperty;
use Lento\Validation\Attributes\{NotBlank, Email, Length, Regex};

/**
 * Undocumented class
 */
class Validator
{
    /**
     * Validates all properties of a DTO with validation attributes.
     * @param object $dto
     * @return array Errors: field => message
     */
    public function validate(object $dto): array
    {
        $errors = [];
        $rc = new ReflectionClass($dto);

        foreach ($rc->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();

            if (method_exists($prop, 'isInitialized') && !$prop->isInitialized($dto)) {
                $value = null;
            } else {
                $value = $prop->getValue($dto);
            }

            #region #NotBlank
            foreach ($prop->getAttributes(NotBlank::class) as $attr) {
                if ($value === null || (is_string($value) && trim($value) === '') || (is_array($value) && count($value) === 0)) {
                    $errors[$name] = 'This value should not be blank.';
                }
            }
            #endregion

            #region Email
            foreach ($prop->getAttributes(Email::class) as $attr) {
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$name] = 'This value is not a valid email address.';
                }
            }
            #endregion

            #region Length
            foreach ($prop->getAttributes(Length::class) as $attr) {
                /** @var Length $inst */
                $inst = $attr->newInstance();
                $len = is_string($value) ? mb_strlen($value) : 0;
                if ($len < $inst->min) {
                    $errors[$name] = "This value is too short. Minimum length is {$inst->min}.";
                } elseif ($len > $inst->max) {
                    $errors[$name] = "This value is too long. Maximum length is {$inst->max}.";
                }
            }
            #endregion

            #region Regex
            foreach ($prop->getAttributes(Regex::class) as $attr) {
                /** @var Regex $inst */
                $inst = $attr->newInstance();
                if ($value !== null && !preg_match($inst->pattern, $value)) {
                    $errors[$name] = "This value does not match the required format.";
                }
            }
            #endregion
        }

        return $errors;
    }
}
