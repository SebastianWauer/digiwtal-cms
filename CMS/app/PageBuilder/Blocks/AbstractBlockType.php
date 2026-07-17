<?php
declare(strict_types=1);

namespace App\PageBuilder\Blocks;

abstract class AbstractBlockType implements BlockTypeInterface
{
    public function definition(): array
    {
        return [
            'label' => $this->label(),
            'defaults' => $this->defaults(),
            'fields' => $this->fields(),
        ];
    }

    /**
     * Validiert und bereinigt Block-Daten anhand von fields().
     * Kann von konkreten Block-Typen überschrieben werden.
     *
     * @param  array<string,mixed> $data  Rohdaten aus dem gespeicherten JSON
     * @return array<string,mixed>        Bereinigter Block (nur bekannte Felder + type)
     */
    public function validate(array $data): array
    {
        $fields = $this->fields();
        $clean  = ['type' => $this->type()];

        // fields() liefert assoziatives Array: ['fieldName' => ['max'=>..., ...]]
        foreach ($fields as $name => $field) {
            $value = $data[$name] ?? null;

            if (is_string($value)) {
                $value = trim($value);
                $max   = $field['max'] ?? null;

                if (is_int($max) && mb_strlen($value) > $max) {
                    $value = mb_substr($value, 0, $max);
                }
            }

            $clean[$name] = $value;
        }

        return $clean;
    }
}
