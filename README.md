This package clones(mirrored) repos from specified server(s), then repos can be put to some disk like dropbox.

Currently available disk: dropbox.

## Requirements

- PHP `^8.0`

## Installation

Clone the repo:

```sh
git clone https://github.com/amirHossein5/git-backup.git

cd git-backup
```

Then you can see available commands via:

```sh
./builds/backup
```

## Getting all repositories from server(s)

For cloning/fetching repositories from server(s), you need a [config file which you specified servers](#configuring-servers),
then pass the config file path to command:

```sh
./builds/backup repo:get --config /path/to/config
```

### Filtering Servers

For filtering servers based on specified name, use option `--matches`:

```sh
./builds/backup repo:get ... --matches=server-name-contains-this
```

Then it will search for servers that their names **contains** specified passed string.

### Filtering Repos

For filtering repo names, use option `--repo-matches`:

```sh
./builds/backup repo:get ... --repo-matches=repo-name-contains-this
```

Then it will search for repositories that their names **contains** specified passed string.

## Putting directory to disk

For putting some **directory** to a [disk](#show-disk-command), run:

```sh
./builds/backup put --dir=/path/upload-dir --disk=disk-name
```

-   It will upload to specified disk, with path `upload-dir/`(passed directory name). Unless [specify destination path](#specifying-destination-path).
-   `--dir` is directory which you want to upload.
-   For option `--disk`, pass the name of disk. [See available disks](#show-disk-command).

### Specifying destination path

By default your directory will be upload to path `passed-directory-name/`. If you want to specfiy custom path that your folder will be upload there, use `--to-dir`

```sh
./builds/backup put --dir=/dir/path --to-dir=some/custom/path
```

Now it will upload to disk path of: `some/custom/path/`.

### Disk Tokens

Sometimes for uploading to some disks, you need some tokens, e.g, dropbox needs key, secret, authorization_token for upload. So define the disk tokens in json file,
then pass path of json file via `--disk-tokens`.

e.g, json file for dropbox:

```json
{
    "key": "app key",
    "secret": "app secret",
    "authorization_token": "app authorization token"
}
```

```sh
./builds/backup put ... --disk-tokens=path/to/disk-auth.json
```

## Show disk command

For seeing available disks run:

```sh
./builds/backup show:disk
```

## Configuration

Config files are in json format.

## Configuring Servers

The base of servers configuration json file:

```json
{
    "servers": [
        ...
    ]
}
```

Each server needs a name, a path that repos be clone there, git clone command, repo names. So:

```json
{
    "servers": [
        {
            "name": "some server name",
            "clone": {
                "to": "/clone/here",
                "using": "git@github.com:/amirhossein5/<repo>"
            },
            "repo-names": {
                "names": ["reponame", "anothername"]
            }
        }
    ]
}
```

-   `clone.using` will be concat to `git clone --mirror`.
-   `<repo>` in `clone.using` stands for each repo name(will be resolve by program).

## Getting repo names from api

If you are using github easily [use](#use-keyword):

```diff
"repo-names": {
-   "names": ["reponame", "anothername"]
+   "use": {
+       "from": "pathto/git-backup/stubs/repo-names.github.json",
+       "with": {
+           "username": "your-github-username"
+       }
+   },
}
```

### Custom api

For getting repository names from api, you can define a api url, with a pattern to get repository names from response of api:

```diff
"repo-names": {
-   "names": ["reponame", "anothername"]
+   "from-api": "https://api.github.com/search/repositories?q=user:yourusername",
+   "pattern": "items.*.name",
+   "token": "if has token"
}
```

`"pattern": "items.*.name"` based on api response means:

```php
[
    // ...
    "items" => [
        0 => [
            "name" => "repo name"
        ]
        1 => // ...,
        2 => // ...,
    ]
]
```

## `use` keyword

> `use` keyword is only available in `repo:get` command

The `use` keyword is for including json keys inside current json file(keys won't be override).

for example if you `use` in `config.json` file:

```json
{
    // ...
    {
        "name": "some name",
        "use": "path/to/file.json"
    }
}
```

e.g, file `path/to/file.json`:

```json
{
    "name": "new",
    "example": "new example",
    "another": {
        "first": "first",
        "second": "second"
    }
}
```

`config.json` file will be render to:

```json
{
    // ...
    {
        "name": "some name",
        "example": "new example",
        "another": {
            "first": "first",
            "second": "second"
        }
    }
}
```

### Using variables with `use`

Define variables value in `use.with`, and write variabe name `-`inside`-`. For example:

file `path/to/file.json`:

```json
{
    "clone": {
        "to": "-clone.to-",
        "using": "-cloneUsing-"
    }
}
```

`config.json` file:

```json
{
    // ...
    {
        "use": {
            "from": "path/to/file.json",
            "with": {
                "clone.to": "clone/here",
                "cloneUsing": "git@github.com:/amirhossein5/<repo>"
            }
        }
    }
}
```

`config.json` file will be render to:

```json
{
    // ...
    {
        "clone": {
            "to": "clone/here",
            "using": "git@github.com:/amirhossein5/<repo>"
        }
    }
}
```

## License

[Licence](https://github.com/amirHossein5/git-backup/blob/main/LICENCE);
