# Release Instructions

## QA

Before any major or minor release, we will run through our [QA plan](https://docs.google.com/spreadsheets/d/1G5mcwtBUhEGAWbPlHD1jrjsEzwndZs7RymoTAKoZr_s/edit#gid=0).

## Process

1. Branch: Starting from `develop`, cut a release branch named `release/X.Y.Z` for your changes.
1. Version bump: Bump the version number in `snapshots.php`.
1. Changelog: Add/update the changelog in `CHANGELOG.md`.
1. Readme updates: Make any other readme changes as necessary.
1. Merge: Make a non-fast-forward merge from your release branch to `develop` (or merge the pull request), then do the same for `develop` into `trunk` (`git checkout trunk && git merge --no-ff develop`). `trunk` contains the stable development version.
1. Push: Push your `trunk` branch to GitHub (e.g. `git push origin trunk`).
1. Release: Create a [new release](https://github.com/10up/snapshots/releases/new), naming the tag and the release with the new version number.  Paste the changelog from `CHANGELOG.md` into the body of the release.