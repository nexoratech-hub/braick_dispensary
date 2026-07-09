<?php
// backend/core/Validator.php

class Validator {
    private $errors = [];
    private $data = [];
    
    public function validate($data, $rules) {
        $this->data = $data;
        $this->errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            $this->applyRule($field, $value, $rule);
        }
        
        return empty($this->errors);
    }
    
    private function applyRule($field, $value, $rule) {
        $rules = explode('|', $rule);
        
        foreach ($rules as $r) {
            if ($r === 'required' && empty($value) && $value !== '0') {
                $this->errors[$field][] = "$field is required";
            }
            
            if ($r === 'email' && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $this->errors[$field][] = "$field must be a valid email";
            }
            
            if (strpos($r, 'min:') === 0) {
                $min = explode(':', $r)[1];
                if (!empty($value) && strlen($value) < $min) {
                    $this->errors[$field][] = "$field must be at least $min characters";
                }
            }
            
            if (strpos($r, 'max:') === 0) {
                $max = explode(':', $r)[1];
                if (!empty($value) && strlen($value) > $max) {
                    $this->errors[$field][] = "$field must be at most $max characters";
                }
            }
            
            if ($r === 'numeric' && !empty($value) && !is_numeric($value)) {
                $this->errors[$field][] = "$field must be a number";
            }
        }
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function getFirstError() {
        foreach ($this->errors as $field => $messages) {
            return $messages[0] ?? null;
        }
        return null;
    }
}
?>