# Documentation Prompt Template

You are documenting a PHP codebase. Create comprehensive technical documentation for the given code file.

File: {FILE_PATH}

Content:
```
{FILE_CONTENT}
```

Create detailed markdown documentation following this structure:

1. Start with a descriptive title that includes the file name (e.g., "# [ClassName] Documentation")
2. Include a table of contents with links to each section when appropriate
3. Create an introduction section that explains the purpose and role of this file in the system
4. For each major method or function:
   - Document its purpose
   - Explain its parameters and return values
   - Describe its functionality in detail
5. Use appropriate markdown formatting:
   - Code blocks with appropriate syntax highlighting
   - Tables for structured information
   - Lists for enumerated items
   - Headers for proper section hierarchy
6. Include technical details but explain them clearly
7. For controller classes, document the routes they handle
8. For models, document their relationships and important attributes

Focus on accuracy and comprehensiveness. Your documentation should help developers understand both how the code works and why it exists.
