#!/usr/bin/env python3
"""Fix remaining WordPress coding standards issues."""

import re
from pathlib import Path

def fix_agents_php():
    """Fix admin/agents.php file."""
    filepath = Path('admin/agents.php')
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Replace WordPress global variables
    replacements = [
        ('$action  = isset', '$agent_action = isset'),
        ('$error   = \'\'', '$agent_error = \'\''),
        ('\n$action ', '\n$agent_action '),
        ('if ( $action && $slug', 'if ( $agent_action && $slug'),
        ('switch ( $action )', 'switch ( $agent_action )'),
        ('\n\t\t$error = ', '\n\t\t$agent_error = '),
        ('\n\t$error = ', '\n\t$agent_error = '),
        ('$search   = isset', '$search_term = isset'),
        ('\'search\'   => $search,', '\'search\'   => $search_term,'),
        ('wp_verify_nonce( $_GET[\'_wpnonce\'] ?? \'\', \'agentic_agent_action\' )',
         'isset( $_GET[\'_wpnonce\'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET[\'_wpnonce\'] ) ), \'agentic_agent_action\' )'),
    ]
    
    for old, new in replacements:
        content = content.replace(old, new)
    
    # Fix Yoda conditions in if statements
    lines = content.split('\n')
    for i, line in enumerate(lines):
        if 'if ' in line or 'elseif ' in line:
            # Fix simple Yoda patterns
            line = re.sub(r'\$agent_data\[\'status\'\]\s*===\s*\'(\w+)\'', r"'\1' === \$agent_data['status']", line)
            line = re.sub(r'(\$\w+)\s*===\s*\'active\'', r"'active' === \1", line)
            line = re.sub(r'(\$\w+)\s*!==\s*\'active\'', r"'active' !== \1", line)
            lines[i] = line
    
    content = '\n'.join(lines)
    
    # Add translator comments for sprintf
    content = re.sub(
        r'(\s+)\$message\s+=\s+sprintf\(\s*\n\s+__\( \'%1\$s activated\.',
        r'\1/* translators: 1: Agent name, 2: Chat URL */\n\1$message     = sprintf(\n\t\t\t__( \'%1$s activated.',
        content
    )
    
    # Add translator comments for other placeholders
    content = content.replace(
        'esc_html__( \'Delete %s', 
        '/* translators: %s: Agent name */\n\t\t\tesc_html__( \'Delete %s'
    )
    
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)
    print(f"Fixed: {filepath}")

def fix_settings_php():
    """Fix admin/settings.php file."""
    filepath = Path('admin/settings.php')
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Add wp_unslash to $_POST sanitization that's missing it
    content = re.sub(
        r'sanitize_text_field\( (\$_POST\[[^\]]+\]) \)',
        r'sanitize_text_field( wp_unslash( \1 ) )',
        content
    )
    
    content = re.sub(
        r'sanitize_email\( (\$_POST\[[^\]]+\]) \)',
        r'sanitize_email( wp_unslash( \1 ) )',
        content
    )
    
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)
    print(f"Fixed: {filepath}")

def main():
    """Run all fixes."""
    fix_agents_php()
    fix_settings_php()
    print("\nAll files fixed!")

if __name__ == '__main__':
    main()
