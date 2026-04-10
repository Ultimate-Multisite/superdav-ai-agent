# Composer Archive Project

This composer plugin adds an `archive-project` command that is similar to the composer
`archive` command, except that it creates a project directory at the root of the archive.

For example, if the `name` field in `composer.json` is `pondermatic/xapi`,
then the archive hierarchy would be:
```
.
|-- xapi/
|   |-- src/
|   |   |-- main.php
|   |-- composer.json
|   |-- readme.md
```
instead of:
```
.
|-- src/
|   |-- main.php
|-- composer.json
|-- readme.md
```

## Usage

Installation can be done with [Composer][composer], by requiring this package as a
development dependency:

```bash
composer require --dev pondermatic/composer-archive-project
```

When using Composer 2.2 or higher, Composer will
[ask for your permission](https://blog.packagist.com/composer-2-2/#more-secure-plugin-execution)
to allow this plugin to execute code. For this plugin to be functional,
permission needs to be granted.

When permission has been granted, the following snippet will be automatically added
to your `composer.json` file by Composer:
```json
{
    "config": {
        "allow-plugins": {
            "pondermatic/composer-archive-project": true
        }
    }
}
```

When using Composer < 2.2, you can add the permission flag ahead of the upgrade
to Composer 2.2, by running:
```bash
composer config allow-plugins.pondermatic/composer-archive-project true
```

Now Composer has a new `archive-project` command. Here is how to use it.
```bash
$ composer help archive-project
Description:
  Creates an archive of this composer package with a root project directory

Usage:
  archive-project [options] [--] [<package> [<version>]]

Arguments:
  package                        The package to archive instead of the current project
  version                        A version constraint to find the package to archive

Options:
  -f, --format=FORMAT            Format of the resulting archive: tar, tar.gz, tar.bz2 or zip (default tar)
      --dir=DIR                  Write the archive to this directory
      --file=FILE                Write the archive with the given file name. Note that the format will be appended.
      --ignore-filters           Ignore filters when saving package
  -h, --help                     Display help for the given command. When no command is given display help for the list command
  -q, --quiet                    Do not output any message
  -V, --version                  Display this application version
      --ansi|--no-ansi           Force (or disable --no-ansi) ANSI output
  -n, --no-interaction           Do not ask any interactive question
      --profile                  Display timing and memory usage information
      --no-plugins               Whether to disable plugins.
      --no-scripts               Skips the execution of all scripts defined in composer.json file.
  -d, --working-dir=WORKING-DIR  If specified, use the given directory as working directory.
      --no-cache                 Prevent use of the cache
  -v|vv|vvv, --verbose           Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Help:
  The archive-project command creates an archive of the specified format
  containing the files and directories of the Composer project or the specified
  package in the specified version and writes it to the specified directory.

  php composer.phar archive-project [--format=zip] [--dir=/foo] [--file=filename] [package [version]]

  Read more at https://getcomposer.org/doc/03-cli.md#archive

```

### Compatibility

This plugin is compatible with:

- PHP **7.2**, **7.3**, **7.4**, and **8.0**
- [Composer][composer] **2.3** and **2.4**

### How it works

The name of the project is taken from the `name` property in your package's
`composer.json` file. For example, if your package name is `pondermatic/xapi`,
then the project name is `xapi`.

## Changelog

### 1.0.0 (2022-09-29)
- Initial release.

## License

Copyright (c) 2022 Pondermatic LLC

Permission is hereby granted, free of charge, to any person obtaining a copy of _this software
and associated documentation files_ (the "Software"), to deal in the Software without restriction,
including without limitation the rights to use, copy, modify, merge, publish, distribute,
sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice _(including the next paragraph)_
shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE
AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

[composer]: https://getcomposer.org/
