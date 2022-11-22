This package [clones/fetches](#getting-all-repositories-from-servers)(mirrored) repos from specified server(s), then repos can be put to some disk like dropbox. Also backup option for [github's gists](#github-gists) is available.

Currently available disk: dropbox.

## Requirements

-   PHP `^8.1`

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

For cloning/fetching repositories from server(s), you need a [config file which servers be specified there](#configuring-servers),
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

## Github Gists

It will get all pages's gists with it's comments. For getting all gists, with their comments(if has any) use:

```sh
./builds/backup gist:get --config gists.hjson --to-dir ~/gists/
```

gists config which contains `username`, `token`:

```hjson
{
    username: "amirHossein5",
    token: "optional"
}
```

Gist files will be saved in structure of `username_gists/gistDescription-id/`, and if gist has any comments, comments will be
saved in gist directory in file `comments.txt`.


### Filtering gists

For filtering gists based on gist description, use option `--desc-matches`:

```sh
./builds/backup gist:get ... --desc-matches 'some gist'
```

## Upload to disk

For putting a **file** to a [disk](#show-disk-command), run:

```sh
./builds/backup put --file=/path-to/backup.zip --disk=dropbox
```

-   It will upload to specified disk, on the root of disk. Unless [specify destination path](#specifying-destination-path).

For putting a **directory** to a [disk](#show-disk-command), run:

```sh
./builds/backup put --dir=/path/upload-dir --disk=dropbox
```

-   It will upload to specified disk, with path `upload-dir/`(passed directory name). Unless [specify destination path](#specifying-destination-path).
-   `--dir` is directory which you want to upload.
-   For option `--disk`, pass the name of disk. [See available disks](#show-disk-command).

### Disk Tokens

Sometimes for uploading to some disks, you need some tokens, e.g, dropbox needs key, secret, authorization_token for upload. So define the disk tokens in json file,
then pass path of json file via `--disk-tokens`.

e.g, json file for dropbox:

```hjson
{
    "key": "app key",
    "secret": "app secret",
    "authorization_token": "app authorization token"
}
```

```sh
./builds/backup put ... --disk-tokens=path/to/disk-auth.json
```

### Options when directory already exists in disk

If directory that you're uploading already exists in the specfied disk, then some options will be show you, like

-   Deleting directory from disk
    -   This just removes the uploaded directory from disk
-   Fresh directory
    -   Deletes uploaded folder, then uploads current directory
-   Selecting new name
    -   Uploads directory with another dir name
-   Replace directory
    -   Replaces directory with previously uploaded one
    -   Files that are same with previously uploaded ones, won't be upload again, (uses uploaded one)
-   Merge directory
    -   Uploads new files, or files that are different with previously uploaded ones
    -   Creates empty directories that aren't exists in uploaded folder
-   Upload remained things
    -   Uploads files, and directories that aren't exists.

When using `replace directory` option, directory which is on the disk, will be moved to `uniqueid.tmp`, then
when upload has finished, the tmp directory will be removed.

### Specifying destination path

By default your directory will be upload to directory which has name of `--dir` directory name option.
Or if it's uploading file, it will be upload to the root of disk.
If you want to specfiy custom path that your folder\file will be upload there, use `--to-dir`

```sh
./builds/backup put ... --to-dir=some/path
```

Now it will be upload to directory of: `some/path/`.

### Logging

To uploaded files, and created directories be logged somewhere you can pass log file path via:

```sh
./builds/backup put ... --log-to=log.txt
```

## Show disk command

For seeing available disks run:

```sh
./builds/backup show:disk
```

## Configuration

Config files are in json or also, [hjson](https://github.com/hjson/hjson-php) is supported.

## Configuring Servers

The base of servers configuration json file:

```hjson
{
    "servers": [
        ...
    ]
}
```

Each server needs a name, a path that repos be clone there, git clone command, repo names. So:

```hjson
{
    "servers": [
        {
            "name": "some server name",
            "clone": {
                "to": "/clone/here",
                "using": "git@github.com:/amirhossein5/<repo>"
            },
            "repoNames": {
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
"repoNames": {
-   "names": ["reponame", "anothername"]
+   "use": {
+       "from": "pathto/git-backup/stubs/repo-names.github.json",
+       "with": {
+           "username": "your-github-username"
+       }
+   },
+   "token": "if has token"
}
```

### Custom api

For getting repository names from api, you can define a api url, with a pattern to get repository names from response of api:

```diff
"repoNames": {
-   "names": ["reponame", "anothername"]
+   "fromApi": "https://api.github.com/search/repositories?q=user:yourusername",
+   "pattern": "items.*.name",
+   "token": "if has token"
}
```

`"pattern": "items.*.name"` based on api response means:

```php
"items" => [
    0 => [
        "name" => "repo name" //...
    ]
    1 => // ...
```

### Using paginated api url

Sometimes the api url has pagination, for that you can expand `fromApi` like this:

```hjson
"repoNames": {
    fromApi:  {
        url: "https://someurl.com/?per_page=50",
        withPagination: true,
        total: 100
        perPage: 50
    }
}
```

`total` also can be get from api too:

```diff
fromApi:  {
    url: "https://someurl.com/?per_page=50",
    withPagination: true,
-   total: 100
+   total: "https://api.github.com/search/repositories?q=user:amirHossein5",
+   totalKey: "total_count"
    perPage: 50
}
```

`totalKey` stands for key of response json which has total.

By default the query string for pages(`?page=2`) is `page` for customizing that pass `pageQueryString`:

```diff
fromApi:  {
    url: "https://someurl.com/?per_page=50",
    withPagination: true,
+   pageQueryString: "pg",
//...
```

## `use` keyword

`use` keyword is only available in `repo:get` command config file.

The `use` keyword is for including json keys inside current json file(keys won't be override).

for example if you `use` in config file:

```hjson
{
    // ...
    {
        "name": "some name",
        "use": "path/to/file.hjson"
    }
}
```

e.g, file `path/to/file.hjson`:

```hjson
{
    "name": "new",
    "example": "new example",
    "another": {
        "first": "first",
        "second": "second"
    }
}
```

config file will be render to:

```hjson
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

file `path/to/file.hjson`:

```hjson
{
    "clone": {
        "to": "-clone.to-",
        "using": "-cloneUsing-"
    }
}
```

config file:

```hjson
{
    // ...
    {
        "use": {
            "from": "path/to/file.hjson",
            "with": {
                "clone.to": "clone/here",
                "cloneUsing": "git@github.com:/amirhossein5/<repo>"
            }
        }
    }
}
```

config file will be render to:

```hjson
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
