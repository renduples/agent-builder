#!/usr/bin/env python3
"""Fix WordPress coding standards issues."""

import re
import sys
from pathlib import Path

def fix_input_sanitization(lines, i):
    """Add wp_unslash() to $_POST and $_GET sanitization."""
    line = lines[i]
    
    # Pattern: sanitize_text_field( $_POST['key'] )
    # Replace with: sanitize_text_field( wp_unslash( $_POST['key'] ) )
    pattern = r'(sanitize_\w+)\(\s*(\$_(POST|GET)\[[^\]]+\])\s*\)'
    match = re.search(pattern, line)
    if match:
        replacement = f'{match.group(1)}( wp_unslash( {match.group(2)} ) )'
        lines[i] = re.sub(pattern, replacement, line)
        return True
    
    return False

def fix_yoda_condition(lines, i):
    """Convert non-Yoda to Yoda conditions."""
    line = lines[i]
    
    # Simple pattern: $var === 'value' -> 'value' === $var
    # Only fix simple cases to avoid breaking complex logic
    patterns = [
        (r'(\$\w+)\s*(===|!==)\s*(["\'][^"\']*["\'])', r'\3 \2 \1'),  # $var === 'string'
        (r'(\$\w+)\s*(===|!==)\s*(\d+)', r'\3 \2 \1'),  # $var === 123
        (r'(\$\w+)\s*(===|!==)\s*(true|false|null)', r'\3 \2 \1'),  # $var === true
    ]
    
    for pattern, replacement in patterns:
        if re.search(pattern, line):
            lines[i] = re.sub(pattern, replacement, line)
            return True
    
    return False

def fix_file(filepath):
    """Fix issues in a single file."""
    with open(filepath, 'r', encoding='utf-8') as f:
        lines = f.readlines()
    
    modified = False
    for i in range(len(lines)):
        # Fix input sanitization
        if '$_POST' in lines[i] or '$_GET' in lines[i]:
            if 'wp_unslash' not in lines[i] and 'sanitize_' in lines[i]:
                if fix_input_sanitization(lines, i):
                    modified = True
        
        # Fix Yoda conditions (only in if statements to avoid breaking assignments)
        if 'if' in lines[i] and ('===' in lines[i] or '!==' in lines[i]):
            if fix_yoda_condition(lines, i):
                modified = True
    
    if modified:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.writelines(lines)
        return True
    return False

def main():
    """Process all PHP files."""
    base_dir = Path(__file__).parent
    php_files = list(base_dir.glob('includes/*.php')) + list(base_dir.glob('admin/*.php'))
    
    fixed_count = 0
    for php_file in php_files:
        if fix_file(php_file):
            print(f"Fixed: {php_file.name}")
            fixed_count += 1
    
    print(f"\nTotal files fixed: {fixed_count}")

if __name__ == '__main__':
    main()
