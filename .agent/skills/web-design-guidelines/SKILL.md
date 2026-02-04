---
name: web-design-guidelines
description: Review UI code for Web Interface Guidelines compliance. Use when asked to "review my UI", "check accessibility", "audit design", "review UX", or "check my site against best practices".
metadata:
  author: vercel
  version: "1.0.0"
  argument-hint: <file-or-pattern>
  category: development
  source:
    repository: https://github.com/vercel-labs/agent-skills
    path: skills/claude.ai/web-design-guidelines
---

# Web Interface Guidelines

Review files for compliance with Web Interface Guidelines.

## How It Works

1. Fetch the latest guidelines from the source URL below
2. Read the specified files (or prompt user for files/pattern)
3. Check against all rules in the fetched guidelines
4. Output findings in the terse `file:line` format

## Guidelines Source

Fetch fresh guidelines before each review:

```
https://raw.githubusercontent.com/vercel-labs/web-interface-guidelines/main/command.md
```

Use WebFetch to retrieve the latest rules. The fetched content contains all the rules and output format instructions.

## Usage

When a user provides a file or pattern argument:

1. Fetch guidelines from the source URL above
2. Read the specified files
3. Apply all rules from the fetched guidelines
4. Output findings using the format specified in the guidelines

If no files specified, ask the user which files to review.

## Output Format

Use the exact format specified in the fetched guidelines, which includes:

- File path and line number
- Rule ID
- Severity level
- Description
- Suggested fix (when applicable)

Example output:

```
src/components/Button.js:42: WIG-001: error: Buttons must have accessible labels
src/styles/main.css:123: WIG-002: warning: Use relative units for font sizes  Fix: Change 'px' to 'rem' or 'em' 
src/components/Header.js:21: WIG-003: info: Consider adding ARIA attributes for better accessibility 
```

## Example 
Fix: Add 'aria-label' or 'aria-labelledby' to the button element. 

## Notes

- Always fetch the latest guidelines before each review
- Apply all rules from the fetched guidelines
- Maintain the exact output format specified in the guidelines
- Include all suggested fixes when available in the guidelines 

## Error Handling

If the guidelines cannot be fetched, inform the user and proceed with the last known good version of the guidelines. If no guidelines are available, explain that a review cannot be performed at this time. 

## Version

1.0.0 - Initial release with full guidelines support and output formatting 

## Author

Vercel Labs 

## License

MIT License

## Source

https://github.com/vercel-labs/web-interface-guidelines 

## Contact  

For questions or issues, please contact the Vercel Labs team at support@vercel.com. 

## Changelog

- 1.0.0: Initial release with full guidelines support and output formatting 

## Dependencies

- WebFetch skill for retrieving guidelines 

## Examples

Review all JavaScript files in the project:

```
review *.js
```

Review a specific component file:

```
review src/components/Header.js 
```

Review all files matching a pattern:

```
review src/**/*.tsx