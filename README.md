# Varnish Plus CLI

This self-contained phar (PHP archive) can be used to work with the Varnish Admin Console VAC.

It provides:
* A command to compile twig templates into a single file.
* A command to deploy the configuration with the VAC API.

## Setup

```bash
$ composer install
```

### Create `.phar` file

```bash
$ make dist
```

## Usage

There is no configuration file for this tool. The expected usage is that you write a makefile or bash script
that calls the tool with the right arguments.

### Compile the VCL with twig

The VAC only supports one single VCL file. It is good practice to separate your VCL into several files for better
overview. With twig, instead of VCL statement `include "sub.vcl`, you will use the twig instruction
`{% include 'sub.vcl.twig' %}`.

Additionally, you can use variables in twig, e.g. to handle different environments.

The `vcl:twig:compile` command configures twig on the specified directory and compiles a template that is the entry
point. You can specify twig variables with the `--twig-variable` option:

```bash
$ ./dist/varnish-plus-cli.phar vcl:twig:compile ../varnish-project/templates envs/local.vcl.twig output.vcl --twig-variable maintenance=1 --twig-variable grace=3600
```

Run `./dist/varnish-plus-cli.phar vcl:twig:compile --help` for a full explanation of all arguments.

### Deploy a VCL to a VAC instance

`vcl:deploy` takes a VCL file and deploys it to a VAC at the location specified by the vcl name and group.

```bash
$ ./dist/varnish-plus-cli.phar vcl:deploy -u https://$HOST --username $USERNAME  --password $PASSWORD --vcl-name $VCL_NAME --vcl-group $VCL_GROUP $FILENAME
```

Run `./dist/varnish-plus-cli.phar vcl:deploy --help` for a full explanation of all arguments.

## Development

We use phpstan and php-cs-fixer for code style purposes:

```bash
$ make phpcs
# to fix code style issues automatically
$ make fix-cs
```
