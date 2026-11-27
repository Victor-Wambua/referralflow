<?php

namespace App\Interop;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/** Validates exported resources against the JSON Schemas in app/Interop/schemas (authored here; R4 subset). */
final class FhirSchemas
{
    /** @return list<string> error messages, empty when valid */
    public static function validate(array $resource): array
    {
        $type = $resource['resourceType'] ?? null;
        $file = __DIR__.'/schemas/'.$type.'.json';
        if (! $type || ! is_file($file)) {
            return ['Unknown resourceType'];
        }
        $validator = new Validator;
        $validator->resolver()->registerFile('urn:referralflow:schema:common', __DIR__.'/schemas/common.json');
        $data = json_decode(json_encode($resource));
        $result = $validator->validate($data, json_decode((string) file_get_contents($file)));
        if ($result->isValid()) {
            return [];
        }
        $fmt = (new ErrorFormatter)->format($result->error(), false);

        return array_map(fn ($k, $v) => "$type: $k: ".(is_array($v) ? implode('; ', $v) : $v), array_keys($fmt), $fmt);
    }

    /** @return list<string> */
    public static function validateBundle(array $bundle): array
    {
        $errors = self::validate($bundle);
        foreach ($bundle['entry'] ?? [] as $i => $e) {
            foreach (self::validate($e['resource'] ?? []) as $m) {
                $errors[] = "entry[$i] $m";
            }
        }

        return $errors;
    }
}
