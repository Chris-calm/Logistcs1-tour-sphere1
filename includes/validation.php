<?php
/**
 * includes/validation.php
 * Form validation and data sanitization
 */

class Validator {
    private $errors = [];
    private $data = [];
    private $rules = [];
    
    /**
     * Constructor
     */
    public function __construct($data = null) {
        if ($data) {
            $this->data = $data;
        }
    }
    
    /**
     * Set data to validate
     */
    public function setData($data) {
        $this->data = $data;
        return $this;
    }
    
    /**
     * Set validation rules
     */
    public function setRules($rules) {
        $this->rules = $rules;
        return $this;
    }
    
    /**
     * Add a validation rule
     */
    public function addRule($field, $label, $rules) {
        $this->rules[] = [
            'field' => $field,
            'label' => $label,
            'rules' => $rules
        ];
        return $this;
    }
    
    /**
     * Run validation
     */
    public function validate() {
        $this->errors = [];
        
        foreach ($this->rules as $rule) {
            $field = $rule['field'];
            $label = $rule['label'];
            $rules = explode('|', $rule['rules']);
            
            $value = $this->data[$field] ?? null;
            
            foreach ($rules as $ruleName) {
                $this->validateRule($field, $label, $value, $ruleName);
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Validate a single rule
     */
    private function validateRule($field, $label, $value, $ruleName) {
        // Parse rule with parameters
        $params = [];
        if (strpos($ruleName, '[') !== false) {
            preg_match('/^([^\[]+)\[(.+)\]$/', $ruleName, $matches);
            if (count($matches) === 3) {
                $ruleName = $matches[1];
                $params = explode(',', $matches[2]);
            }
        }
        
        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->addError($field, "The {$label} field is required");
                }
                break;
                
            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "The {$label} must be a valid email address");
                }
                break;
                
            case 'min':
                $min = $params[0] ?? 0;
                if (!empty($value) && strlen($value) < $min) {
                    $this->addError($field, "The {$label} must be at least {$min} characters");
                }
                break;
                
            case 'max':
                $max = $params[0] ?? 999;
                if (!empty($value) && strlen($value) > $max) {
                    $this->addError($field, "The {$label} must not exceed {$max} characters");
                }
                break;
                
            case 'min_value':
                $min = $params[0] ?? 0;
                if (!empty($value) && $value < $min) {
                    $this->addError($field, "The {$label} must be at least {$min}");
                }
                break;
                
            case 'max_value':
                $max = $params[0] ?? 999999;
                if (!empty($value) && $value > $max) {
                    $this->addError($field, "The {$label} must not exceed {$max}");
                }
                break;
                
            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, "The {$label} must be a number");
                }
                break;
                
            case 'integer':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, "The {$label} must be an integer");
                }
                break;
                
            case 'alpha':
                if (!empty($value) && !ctype_alpha($value)) {
                    $this->addError($field, "The {$label} must contain only letters");
                }
                break;
                
            case 'alnum':
                if (!empty($value) && !ctype_alnum($value)) {
                    $this->addError($field, "The {$label} must contain only letters and numbers");
                }
                break;
                
            case 'phone':
                if (!empty($value) && !preg_match('/^[\+]?[0-9\-\(\)\s]{7,20}$/', $value)) {
                    $this->addError($field, "The {$label} must be a valid phone number");
                }
                break;
                
            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, "The {$label} must be a valid URL");
                }
                break;
                
            case 'date':
                if (!empty($value) && !strtotime($value)) {
                    $this->addError($field, "The {$label} must be a valid date");
                }
                break;
                
            case 'date_after':
                $date = $params[0] ?? 'today';
                $compareDate = strtotime($date);
                if (!empty($value) && strtotime($value) < $compareDate) {
                    $this->addError($field, "The {$label} must be after {$date}");
                }
                break;
                
            case 'date_before':
                $date = $params[0] ?? 'today';
                $compareDate = strtotime($date);
                if (!empty($value) && strtotime($value) > $compareDate) {
                    $this->addError($field, "The {$label} must be before {$date}");
                }
                break;
                
            case 'matches':
                $matchField = $params[0] ?? '';
                $matchValue = $this->data[$matchField] ?? '';
                if ($value !== $matchValue) {
                    $this->addError($field, "The {$label} does not match");
                }
                break;
                
            case 'in':
                $allowedValues = $params;
                if (!empty($value) && !in_array($value, $allowedValues)) {
                    $this->addError($field, "The {$label} value is not allowed");
                }
                break;
                
            case 'unique':
                list($table, $column) = $params;
                if (!empty($value)) {
                    $this->validateUnique($field, $label, $value, $table, $column);
                }
                break;
                
            case 'exists':
                list($table, $column) = $params;
                if (!empty($value)) {
                    $this->validateExists($field, $label, $value, $table, $column);
                }
                break;
        }
    }
    
    /**
     * Validate unique constraint
     */
    private function validateUnique($field, $label, $value, $table, $column) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?");
            $stmt->execute([$value]);
            $result = $stmt->fetch();
            
            if ($result && $result['count'] > 0) {
                $this->addError($field, "The {$label} already exists");
            }
        } catch (Exception $e) {
            // Silently fail if table doesn't exist
        }
    }
    
    /**
     * Validate exists constraint
     */
    private function validateExists($field, $label, $value, $table, $column) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?");
            $stmt->execute([$value]);
            $result = $stmt->fetch();
            
            if (!$result || $result['count'] == 0) {
                $this->addError($field, "The selected {$label} does not exist");
            }
        } catch (Exception $e) {
            // Silently fail if table doesn't exist
        }
    }
    
    /**
     * Add error
     */
    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    /**
     * Get errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Get first error for a field
     */
    public function getFirstError($field) {
        if (isset($this->errors[$field]) && !empty($this->errors[$field])) {
            return $this->errors[$field][0];
        }
        return null;
    }
    
    /**
     * Get all errors as array
     */
    public function getErrorArray() {
        $flat = [];
        foreach ($this->errors as $field => $messages) {
            $flat[$field] = $messages[0] ?? '';
        }
        return $flat;
    }
    
    /**
     * Check if field has error
     */
    public function hasError($field) {
        return isset($this->errors[$field]) && !empty($this->errors[$field]);
    }
    
    /**
     * Get validated data
     */
    public function getValidatedData() {
        $data = [];
        foreach ($this->rules as $rule) {
            $field = $rule['field'];
            $data[$field] = $this->data[$field] ?? null;
        }
        return $data;
    }
}

// ============================================
// Helper Functions for Validation
// ============================================

/**
 * Quick validation function
 */
function validate($data, $rules) {
    $validator = new Validator($data);
    $validator->setRules($rules);
    return $validator;
}

/**
 * Validate required field
 */
function isRequired($value) {
    return !empty($value) || $value === '0';
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate URL
 */
function isValidURL($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Validate phone number
 */
function isValidPhone($phone) {
    return preg_match('/^[\+]?[0-9\-\(\)\s]{7,20}$/', $phone);
}

/**
 * Validate date
 */
function isValidDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Validate password strength
 */
function isStrongPassword($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'Minimum 8 characters';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'At least one uppercase letter';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'At least one lowercase letter';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'At least one number';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'At least one special character';
    }
    
    return $errors;
}

/**
 * Sanitize input
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Escape output for HTML
 */
function escape($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}