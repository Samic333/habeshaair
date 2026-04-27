<?php
/**
 * validation.php — small hand-rolled validators returning [errors, clean].
 */

class V {
    public array $errors = [];
    public array $clean  = [];
    private array $input;

    public function __construct(array $input) { $this->input = $input; }

    private function val(string $field) {
        $v = $this->input[$field] ?? null;
        if (is_string($v)) $v = trim($v);
        return $v;
    }

    public function required(string $field, string $label): self {
        $v = $this->val($field);
        if ($v === null || $v === '' || $v === []) {
            $this->errors[$field] = "$label is required.";
        } else {
            $this->clean[$field] = $v;
        }
        return $this;
    }

    public function optional(string $field): self {
        $v = $this->val($field);
        if ($v !== null && $v !== '') $this->clean[$field] = $v;
        return $this;
    }

    public function email(string $field, string $label): self {
        if (!isset($this->errors[$field]) && isset($this->clean[$field])) {
            if (!filter_var($this->clean[$field], FILTER_VALIDATE_EMAIL)) {
                $this->errors[$field] = "$label must be a valid email address.";
                unset($this->clean[$field]);
            }
        }
        return $this;
    }

    public function phone(string $field, string $label): self {
        if (!isset($this->errors[$field]) && isset($this->clean[$field])) {
            $digits = preg_replace('/[^\d+]/', '', $this->clean[$field]);
            if (strlen($digits) < 7 || strlen($digits) > 20) {
                $this->errors[$field] = "$label must include country code (e.g. +254...).";
                unset($this->clean[$field]);
            } else {
                $this->clean[$field] = $digits;
            }
        }
        return $this;
    }

    public function in(string $field, array $allowed, string $label): self {
        if (!isset($this->errors[$field]) && isset($this->clean[$field])) {
            if (!in_array($this->clean[$field], $allowed, true)) {
                $this->errors[$field] = "$label is invalid.";
                unset($this->clean[$field]);
            }
        }
        return $this;
    }

    public function maxLen(string $field, int $max, string $label): self {
        if (!isset($this->errors[$field]) && isset($this->clean[$field])) {
            if (mb_strlen((string)$this->clean[$field]) > $max) {
                $this->errors[$field] = "$label is too long (max $max).";
                unset($this->clean[$field]);
            }
        }
        return $this;
    }

    public function intRange(string $field, int $min, int $max, string $label): self {
        if (!isset($this->errors[$field]) && isset($this->clean[$field]) && $this->clean[$field] !== '') {
            $n = (int)$this->clean[$field];
            if ((string)$n !== (string)$this->clean[$field] || $n < $min || $n > $max) {
                $this->errors[$field] = "$label must be between $min and $max.";
                unset($this->clean[$field]);
            } else {
                $this->clean[$field] = $n;
            }
        }
        return $this;
    }

    public function date(string $field, string $label): self {
        if (!isset($this->errors[$field]) && isset($this->clean[$field]) && $this->clean[$field] !== '') {
            $d = DateTime::createFromFormat('Y-m-d', (string)$this->clean[$field]);
            if (!$d || $d->format('Y-m-d') !== $this->clean[$field]) {
                $this->errors[$field] = "$label is not a valid date (YYYY-MM-DD).";
                unset($this->clean[$field]);
            }
        }
        return $this;
    }

    public function bool(string $field): self {
        $this->clean[$field] = !empty($this->input[$field]) ? 1 : 0;
        return $this;
    }

    public function ok(): bool { return empty($this->errors); }
}

function honeypot_caught(): bool {
    return !empty($_POST['_hp']);
}
