#!/usr/bin/env python3
"""Fix inline comments and docblock parameters to end with periods."""

import re
import sys
from pathlib import Path

def fix_file(filepath):
    """Fix comments in a single file."""
    with open(filepath, 'r', encoding='utf-8') as f:
        lines = f.readlines()
    
    modified = False
    for i, line in enumerate(lines):
        original = line
        
        # Fix inline comments (// comment without ending punctuation)
        if re.match(r'^\s*//[^/].*[^.!?\s]$', line):
            lines[i] = line.rstrip() + '.\n'
            modified = True
        
        # Fix docblock parameter comments (@param ... without ending punctuation)
        elif re.match(r'^\s*\*\s*@param\s+.*[^.!?\s]$', line):
            lines[i] = line.rstrip() + '.\n'
            modified = True
    
    if modified:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.writelines(lines)
        return True
    return False

def main():
    """Process all PHP files in includes/ and admin/ directories."""
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
