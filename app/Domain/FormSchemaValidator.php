<?php

namespace App\Domain;

use App\Models\FormSchema;
use Illuminate\Support\Facades\Validator;

/** Turns a versioned field-definition list into Laravel validation rules and cleaned values. */
final class FormSchemaValidator
{
    public const TYPES = ['text', 'textarea', 'select', 'number', 'date', 'boolean'];

    public static function rules(FormSchema $schema, string $prefix = 'form'): array
    {
        $rules = [];
        foreach ($schema->fields as $f) {
            $r = [($f['required'] ?? false) ? 'required' : 'nullable'];
            $r = array_merge($r, match ($f['type']) {
                'text' => ['string', 'max:'.($f['max'] ?? 200)],
                'textarea' => ['string', 'max:'.($f['max'] ?? 1000)],
                'select' => ['string', 'in:'.implode(',', array_column($f['options'] ?? [], 'value'))],
                'number' => ['numeric'],
                'date' => ['date'],
                'boolean' => ['boolean'],
                default => ['string'],
            });
            $rules["$prefix.{$f['key']}"] = $r;
        }

        return $rules;
    }

    /** Validate a field-definition list itself (for the admin editor). Returns error strings. */
    public static function definitionErrors(mixed $fields): array
    {
        if (! is_array($fields) || $fields === []) {
            return ['Fields must be a non-empty list.'];
        }
        $errors = [];
        $keys = [];
        foreach ($fields as $i => $f) {
            $n = $i + 1;
            if (! is_array($f) || empty($f['key']) || ! preg_match('/^[a-z][a-z0-9_]{0,40}$/', (string) $f['key'])) {
                $errors[] = "Field $n: key must be lower-case letters, digits and underscores.";

                continue;
            }
            if (isset($keys[$f['key']])) {
                $errors[] = "Field $n: duplicate key {$f['key']}.";
            }
            $keys[$f['key']] = true;
            if (empty($f['label'])) {
                $errors[] = "Field $n: label is required.";
            }
            if (! in_array($f['type'] ?? null, self::TYPES, true)) {
                $errors[] = "Field $n: type must be one of ".implode(', ', self::TYPES).'.';
            }
            if (($f['type'] ?? null) === 'select' && empty($f['options'])) {
                $errors[] = "Field $n: select needs options.";
            }
        }

        return $errors;
    }

    public static function validator(FormSchema $schema, array $input)
    {
        return Validator::make($input, self::rules($schema));
    }
}
