# Release Process

This page is the single source of truth for cutting a release of GatherPress
Alpha. The mechanics are automated by
[`.github/workflows/release.yml`](../.github/workflows/release.yml); this
doc covers what to expect, what to verify, and what to do when something
doesn't go as planned.

GatherPress Alpha ships in lockstep with GatherPress core — every GatherPress
release has a matching Alpha release with the same version number. The Alpha
plugin carries the migration code needed to bridge breaking changes between
versions and goes away at GatherPress 1.0.0.

## What gets automated

Pushing a tag of the form `X.Y.Z` (stable) or `X.Y.Z-alpha.N` / `-beta.N` /
`-rc.N` (pre-release) triggers `release.yml`. The workflow:

| Tag pattern             | Distro zip                            | GitHub Release entry | Changelog body source                                                          |
| ----------------------- | ------------------------------------- | -------------------- | ------------------------------------------------------------------------------ |
| `0.34.0`                | `gatherpress-alpha.0.34.0.zip`        | Release (latest)     | Rolled-up `[0.34.0]` section, committed back to `CHANGELOG.md` via auto-PR     |
| `0.34.0-alpha.1`        | `gatherpress-alpha.0.34.0-alpha.1.zip`| Pre-Release          | Rolled-up `[0.34.0-alpha.1]` section computed in an ephemeral checkout (no commit) |
| `0.34.0-beta.1` / `-rc.1` | Same shape as alpha                 | Pre-Release          | Same shape as alpha                                                            |

GatherPress Alpha does **not** deploy to wordpress.org. It is distributed only
via GitHub releases. The distro zip's outer filename carries the version; the
inner layout is always `gatherpress-alpha/...` so it installs cleanly under
the right slug.

## Pre-release flow

**Use case.** You want testers running matched pre-release builds of
GatherPress core to be able to download the corresponding Alpha build.

**Cut it:**

```bash
git checkout main
git pull origin main
git tag 0.34.0-alpha.1
git push origin 0.34.0-alpha.1
```

**What the workflow does:**

1. Detects the tag is a pre-release (the `-alpha.` / `-beta.` / `-rc.` suffix).
2. Builds `gatherpress-alpha.0.34.0-alpha.1.zip` via `npm run plugin-zip`.
3. Runs `composer changelog:write --use-version=0.34.0-alpha.1 ...` in an ephemeral working copy and extracts the resulting `[0.34.0-alpha.1]` section as the release body. The changes never get committed anywhere — they evaporate when the job ends.
4. Creates a GitHub **Pre-Release** with the zip attached and the rolled-up body. The Pre-Release is **not** marked as the latest release.
5. **`.github/changelog/*` entries are left in place** so the eventual stable release still has them.

**Verify after the workflow lands:**

- [GitHub Releases page](https://github.com/GatherPress/gatherpress-alpha/releases) shows the new tag with a "Pre-release" badge.
- The attached zip downloads as `gatherpress-alpha.X.Y.Z-alpha.N.zip` and unzips with a `gatherpress-alpha/` top-level directory.

## Stable release flow

**Pre-flight checklist:**

- [ ] GatherPress core has the matching version queued (or just shipped).
- [ ] `Version:` header in `gatherpress-alpha.php` matches the tag you're about to push.
- [ ] `[Unreleased]`-equivalent entries in `.github/changelog/` read correctly (skim them).

**Cut it:**

```bash
git checkout main
git pull origin main
git tag 0.34.0
git push origin 0.34.0
```

**What the workflow does:**

1. Detects the tag is stable (no `-alpha.` / `-beta.` / `-rc.` suffix).
2. Runs `composer changelog:write --use-version=0.34.0 --release-date=<today> --add-pr-num --deduplicate=-1 --yes`. This:
    - Aggregates every file in `.github/changelog/` into a new `## [0.34.0] - YYYY-MM-DD` section at the top of `CHANGELOG.md`.
    - Appends `[#NNNN]` to each entry from the originating PR's merge commit subject.
    - **Deletes** the entry files so the next cycle starts clean.
3. Commits the rolled-up `CHANGELOG.md` + the deleted entry files to a new `release/0.34.0` branch and **opens an auto-PR back to `main`** for the release manager to merge after the release ships. The auto-PR carries the `Skip Changelog` label so the changelog gate doesn't block it.
4. Builds `gatherpress-alpha.0.34.0.zip`.
5. Extracts the newly-written `[0.34.0]` section from `CHANGELOG.md` as the release body.
6. Creates a GitHub **Release** with the zip attached, marked as **latest**.

**Verify after the workflow lands:**

- [GitHub Releases page](https://github.com/GatherPress/gatherpress-alpha/releases) shows the new tag as "Latest release".
- The release body matches the `[0.34.0]` section that just landed in `CHANGELOG.md` on the `release/0.34.0` branch.
- The auto-PR titled "Roll up changelog for 0.34.0" is open against `main`. **Merge this once you've confirmed the release body renders correctly** — that returns the rolled-up `CHANGELOG.md` and the cleaned `.github/changelog/` to the long-lived branch.

## Troubleshooting

### "Rolled-up CHANGELOG.md does not contain a `[X.Y.Z]` section"

The `rollup` job failed. Almost always means `.github/changelog/` was empty at
tag time. Confirm by checking out the tag locally and looking:

```bash
git checkout 0.34.0
ls .github/changelog/
```

If it's truly empty, the release shouldn't go out — there's nothing to ship.
If there are entries but the rollup still failed, run the same command locally
to reproduce.

### Auto-PR for the changelog rollup didn't open

Check the workflow run log. If `release/X.Y.Z` exists on `origin` with the
rollup commit but no PR opened, open it manually:

```bash
gh pr create \
  --base main \
  --head release/0.34.0 \
  --title "Roll up changelog for 0.34.0" \
  --body "Automated rollup of .github/changelog/* entries." \
  --label "Skip Changelog"
```

### I tagged the wrong commit

Delete the tag locally and on origin, then re-tag:

```bash
git tag -d 0.34.0
git push --delete origin 0.34.0
# fix up main, then re-tag
git tag 0.34.0
git push origin 0.34.0
```

If the release workflow already ran against the bad tag, delete the GitHub
Release entry (it'll auto-recreate on the new tag push) and close the bogus
auto-PR.

## Versioning conventions

- **Stable**: `0.34.0`, `0.35.0`, `1.0.0`. Three numeric components, no suffix.
- **Alpha**: `0.34.0-alpha.1`, `0.34.0-alpha.2`. Use for early in-cycle builds.
- **Beta**: `0.34.0-beta.1`. Use for feature-complete in-cycle builds.
- **Release candidate**: `0.34.0-rc.1`. Use for "we believe this is shippable."

The version must always match the GatherPress core version that Alpha is
bridging from. Alpha's coexistence guard refuses to boot if
`GATHERPRESS_VERSION !== GATHERPRESS_ALPHA_VERSION`.
