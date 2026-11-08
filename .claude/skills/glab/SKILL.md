---
name: glab
description: GitLab CLI interaction for issues, merge requests, and epics
license: MIT
compatibility: opencode
---

## What I do

- Fetch issue details and context using `glab issue view`
- View merge request details and diffs using `glab mr view` and `glab mr diff`
- Access epic information via GitLab API
- Post code review comments on merge requests
- Gather context before starting tasks
- Never approve merge requests

## When to use me

Use this skill when you need to:
- Get context about an issue, MR, or epic
- Review code changes in a specific merge request
- Post feedback on merge requests
- Reference issues or merge requests in discussions

## Commands

### View Issue
```bash
glab issue view <number>
```
Returns: Issue details, description, labels, assignees, comments

### View Merge Request
```bash
glab mr view <number>
```
Returns: MR details, source/target branches, author, pipeline status, description

### View MR Diff
```bash
glab mr diff <number>
```
Returns: Actual code changes in the merge request

### List Issues/MRs
```bash
glab issue list
glab mr list
```

### View Epic (via API)
```bash
glab api groups/ManagementDrives%2Fdevelopers%2Fai/epics/<epic_id>
```
Returns: Epic details, title, description, dates, labels

### Post MR Review
```bash
glab mr note <number> --message "Your review content"
```

## Reference Format

- **Issue**: `#N` or `Issue #N` (e.g., `#1`, `Issue #1`)
- **MR**: `!N` or `MR !N` (e.g., `!3`, `MR !3`)
- **Epic**: `&N` or `Epic &N` (e.g., `&1`, `Epic &1`)

## Important Rules

- ✅ **Always use** `glab mr view <number>` to get MR context before reviewing
- ✅ **Always use** `glab mr diff <number>` to see actual code changes
- ❌ **Never approve** merge requests - only provide feedback and analysis
- ✅ **Post reviews** as comments on GitLab for team visibility

## Example Workflow

```bash
# Get epic context
glab api groups/ManagementDrives%2Fdevelopers%2Fai/epics/1

# Get issue details
glab issue view 1

# Get MR details
glab mr view 3

# Get MR diff
glab mr diff 3

# Post review
glab mr note 3 --message "$(cat <<'EOF'
# Code Review

## Summary
[Your review content]
EOF
)"
```

## Reference

See ``glab --help`` for detailed command reference.
