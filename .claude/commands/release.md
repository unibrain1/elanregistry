# Create a file in .claude/commands/
# Example: .claude/commands/release.md

---
description: Release a new version of the project with comprehensive workflow
---

# How to release a new version of the project

Follow this comprehensive release process to ensure proper versioning, changelog updates, and deployment.

## 1. Pre-Release Analysis

### Check Current State
```bash
# Verify you're on the main branch and up to date
git checkout main
git pull origin main
git status
```

### Identify Last Release
```bash
# Find the last release tag
git tag --sort=-version:refname | head -10
git describe --tags --abbrev=0

# Or check specific tag pattern
git tag -l "v*" --sort=-version:refname | head -5
```

### Analyze Changes Since Last Release
```bash
# Get commit history since last tag
LAST_TAG=$(git describe --tags --abbrev=0)
echo "Changes since $LAST_TAG:"

# Detailed commit log with files changed
git log $LAST_TAG..HEAD --oneline --stat

# Just commit messages for changelog
git log $LAST_TAG..HEAD --pretty=format:"- %s (%h)"

# Group by type (if using conventional commits)
git log $LAST_TAG..HEAD --pretty=format:"%s" | grep -E "^(feat|fix|docs|style|refactor|test|chore)"
```

### Analyze Impact and Determine Version Bump
```bash
# Count commits by type
echo "=== COMMIT ANALYSIS ==="
echo "Features (minor bump):"
git log $LAST_TAG..HEAD --pretty=format:"%s" | grep -c "^feat"

echo "Bug fixes (patch bump):"
git log $LAST_TAG..HEAD --pretty=format:"%s" | grep -c "^fix"

echo "Breaking changes (major bump):"
git log $LAST_TAG..HEAD --grep="BREAKING CHANGE" --oneline | wc -l

echo "Other changes:"
git log $LAST_TAG..HEAD --pretty=format:"%s" | grep -v -E "^(feat|fix)" | wc -l
```

### Check Files Changed
```bash
# See which files have been modified
git diff $LAST_TAG..HEAD --name-only

# Focus on important files
git diff $LAST_TAG..HEAD --name-only | grep -E "(package\.json|README\.md|CHANGELOG\.md)"

# Check for dependency changes
git diff $LAST_TAG..HEAD package.json
```

## 2. Version Determination

Based on the analysis above, determine the new version following [Semantic Versioning](https://semver.org/):

- **Major (X.0.0)**: Breaking changes, incompatible API changes
- **Minor (X.Y.0)**: New features, backwards compatible
- **Patch (X.Y.Z)**: Bug fixes, backwards compatible

```bash
# Get current version from package.json
CURRENT_VERSION=$(node -p "require('./package.json').version")
echo "Current version: $CURRENT_VERSION"

# Calculate next version (replace with appropriate bump)
# For patch: npm version patch --no-git-tag-version
# For minor: npm version minor --no-git-tag-version
# For major: npm version major --no-git-tag-version
```

## 3. Update Documentation

### Update CHANGELOG.md
```bash
# Create backup
cp CHANGELOG.md CHANGELOG.md.backup

# Edit CHANGELOG.md to add new version section
# Include:
# - Version number and date
# - Added features
# - Fixed bugs
# - Changed functionality
# - Deprecated features
# - Removed features
# - Security fixes
```

**CHANGELOG.md format example:**
```markdown
## [X.Y.Z] - YYYY-MM-DD

### Added
- New feature descriptions

### Changed
- Modified functionality

### Fixed
- Bug fix descriptions

### Security
- Security improvements
```

### Update README.md if needed
```bash
# Check if README needs updates for new features
git diff $LAST_TAG..HEAD README.md

# Update version badges, installation instructions, or feature lists if necessary
```

## 4. Version Bump and Tagging

### Update Package Version
```bash
# Bump version in package.json (choose appropriate level)
npm version patch --no-git-tag-version  # for patch
# npm version minor --no-git-tag-version  # for minor
# npm version major --no-git-tag-version  # for major

# Verify new version
NEW_VERSION=$(node -p "require('./package.json').version")
echo "New version: $NEW_VERSION"
```

### Commit Release Changes
```bash
# Stage all release-related changes
git add package.json CHANGELOG.md README.md

# Create release commit
git commit -m "chore: release v$NEW_VERSION

- Update version to $NEW_VERSION
- Update CHANGELOG.md with release notes
- Update documentation as needed"
```

### Create and Push Tag
```bash
# Create annotated tag with release notes
git tag -a "v$NEW_VERSION" -m "Release v$NEW_VERSION

$(git log $LAST_TAG..HEAD --pretty=format:"- %s (%h)" | head -20)"

# Push commit and tag
git push origin main
git push origin "v$NEW_VERSION"
```

## 5. Build and Test

### Run Full Test Suite
```bash
# Install dependencies
npm ci

# Run all tests
npm run test
npm run lint
npm run type-check

# Build project
npm run build

# Test build output
npm run start  # or appropriate command to test build
```

## 6. Create GitHub Release

### Using GitHub CLI
```bash
# Create GitHub release with auto-generated notes
gh release create "v$NEW_VERSION" \
  --title "Release v$NEW_VERSION" \
  --generate-notes \
  --verify-tag

# Or create with custom notes
gh release create "v$NEW_VERSION" \
  --title "Release v$NEW_VERSION" \
  --notes-file RELEASE_NOTES.md \
  --verify-tag
```

### Manual Release Notes Template
Create `RELEASE_NOTES.md`:
```markdown
## What's Changed

### 🚀 Features
- List new features

### 🐛 Bug Fixes
- List bug fixes

### 📚 Documentation
- Documentation updates

### 🔧 Maintenance
- Internal changes

**Full Changelog**: https://github.com/USER/REPO/compare/PREVIOUS_TAG...v$NEW_VERSION
```

## 7. Post-Release Tasks

### Verify Release
```bash
# Verify tag exists
git tag -l "v$NEW_VERSION"

# Verify GitHub release
gh release view "v$NEW_VERSION"

# Check if package published (if applicable)
# npm view PACKAGE_NAME versions --json
```

### Update Development Branch (if using)
```bash
# If you have a development branch, merge back
git checkout develop  # or your dev branch name
git merge main
git push origin develop
```

### Cleanup
```bash
# Remove backup files
rm -f CHANGELOG.md.backup RELEASE_NOTES.md

# Verify clean state
git status
```

## 8. Communication

- [ ] Update project documentation/wiki if needed
- [ ] Notify team/users about the release
- [ ] Update deployment environments
- [ ] Monitor for any post-release issues

## Useful Commands for Release Management

### Find Specific Types of Changes
```bash
# Breaking changes
git log $LAST_TAG..HEAD --grep="BREAKING CHANGE"

# Security fixes
git log $LAST_TAG..HEAD --grep="security\|Security\|CVE"

# Performance improvements
git log $LAST_TAG..HEAD --grep="perf\|performance\|optimize"
```

### Release Branch Workflow (Alternative)
```bash
# Create release branch for final preparations
git checkout -b release/v$NEW_VERSION
# Make final adjustments, then merge back to main
```

### Rollback if Needed
```bash
# Delete tag if something went wrong
git tag -d "v$NEW_VERSION"
git push origin :refs/tags/"v$NEW_VERSION"

# Revert release commit
git revert HEAD
```

## Best Practices

- **Always test before releasing** - Run full test suite and manual testing
- **Use semantic versioning** consistently
- **Keep detailed changelogs** for user clarity
- **Coordinate releases** with team members
- **Monitor post-release** for issues
- **Use release branches** for complex releases
- **Automate where possible** but verify each step
- **Tag consistently** using the same format (e.g., v1.2.3)

Remember to follow your project's specific release guidelines and coordinate with your team before publishing releases.

