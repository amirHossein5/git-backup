# Release Notes

## v0.5.0

### Added
 - Writing a server configuration without having servers key: [1fcb62aef0](https://github.com/amirHossein5/git-backup/commit/1fcb62aef0fd5bbb4202d854b0158f436d2532ee)
 
 ```diff
- {
-    servers: [
        {
            name: some name
            clone: {
                to: /clone/here
                using: git@github.com:/amirhossein5/<repo>
            },
            repoNames: {
                names: ["reponame", "anothername"]
            }
        }
-    ]
-}
 ```

## v0.4.0

### Changed

- Change defining variable names for `use.with` from being inside of `-` to `<`varname`>`

## v0.3.3

### Fixed

-   Fix being unable to resolve `~` as home directory in `clone.to` and `use.from` in `repo:get` command config.

## v0.3.2

Fix filtering gists.

## v0.3.0

### Changed

-   `from-api` changed to `fromApi` in `repo:get` config.
-   `repo-names` changed to `repoNames` in `repo:get` config.

### Added

-   Added backuping github gists feature.
-   Added option `--file` for uploading file in `put` command.
-   Added fresh,upload remained options when directory already exists in `put` command.
-   Added log behaviour to `put` command.
-   Output message improvements of `put` command.
-   Added support for paginated urls for `repo:get` config.

## v0.2.1

### Fixed

-   Fixed being unable to upload empty files.
-   Fixed not showing first item of upload in progress bar.
-   Fixed wrong output messages.

### Added

-   Added replace, rename, merge options when directory already exists.
-   Added output for showing total uploaded file size, and file size of uploading file.

## v0.1.0

init
