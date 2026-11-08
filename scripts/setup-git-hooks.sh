#!/bin/bash

# Setup git hooks for the project
# This script configures git hooks to run code style fixes before commits

HOOK_DIR=".git/hooks"
PRE_COMMIT_HOOK="$HOOK_DIR/pre-commit"

# Create hooks directory if it doesn't exist
mkdir -p "$HOOK_DIR"

# Create pre-commit hook
cat > "$PRE_COMMIT_HOOK" << 'EOF'
#!/bin/bash

# Pre-commit hook: Fix code style on staged files
echo "Running code style fixes on staged files..."
make fix-code-staged

# Re-add fixed files to staging
git diff --name-only --cached | grep '^backend/' | xargs -r git add

exit 0
EOF

# Make the hook executable
chmod +x "$PRE_COMMIT_HOOK"

echo "✅ Git hooks configured successfully!"
echo "Pre-commit hook will run 'make fix-code-staged' automatically"

