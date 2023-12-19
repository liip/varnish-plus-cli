# Varnish Plus CLI

[![Download latest release](https://img.shields.io/github/tag/liip/varnish-plus-cli.svg?label=release)](https://github.com/liip/varnish-plus-cli/releases/latest/download/varnish-plus-cli.phar)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)
[![CI](https://github.com/liip/varnish-plus-cli/actions/workflows/ci.yaml/badge.svg)](https://github.com/liip/varnish-plus-cli/actions/workflows/ci.yaml)

This self-contained phar (PHP archive) can be used to work with the commercial version of Varnish.

It provides:
* A command to compile twig templates into a single file.
* Commands to deploy the configuration to the Varnish Controller or the legacy Varnish Admin Console (VAC).

## Installation

```bash
$ wget https://github.com/liip/varnish-plus-cli/releases/latest/download/varnish-plus-cli.phar
$ chmod u+x varnish-plus-cli.phar
```

## Usage

There is no configuration file for this tool. The expected usage is that you write a makefile or bash script
that calls the tool with the right arguments.

### Compile the VCL with twig

The VAC only supports one single VCL file. Varnish Controller would support multiple files, but it is simpler to manage
a single file on the server.

It is good practice to separate your VCL into several files for better readability. With Twig, instead of VCL statement
`include "sub.vcl`, you will use the twig instruction `{% include 'sub.vcl.twig' %}`.

Additionally, you can use variables in twig, e.g. to handle different environments.

The `vcl:twig:compile` command configures twig on the specified directory and compiles a template that is the entry
point. You can specify twig variables with the `--twig-variable` option:

```bash
$ ./varnish-plus-cli.phar vcl:twig:compile ../varnish-project/templates envs/local.vcl.twig output.vcl --twig-variable maintenance=1 --twig-variable grace=3600
```

Run `./varnish-plus-cli.phar vcl:twig:compile --help` for a full explanation of all arguments.

### Deploy a VCL to a Varnish Controller instance

`varnish-controller:deploy` takes a VCL file and deploys it to a Varnish Controller into the specified group.

```bash
$ ./varnish-plus-cli.phar vac:deploy -u https://$HOST --organization $ORGANIZATION --username $USERNAME  --password $PASSWORD --vcl-name $VCL_NAME --vcl-group $VCL_GROUP $FILENAME
```

Run `./dist/varnish-plus-cli.phar varnish-controller:deploy --help` for a full explanation of all arguments.

### Deploy a VCL to a VAC instance

`vac:deploy` takes a VCL file and deploys it to a VAC at the location specified by the vcl name and group.

```bash
$ ./varnish-plus-cli.phar vac:deploy -u https://$HOST --username $USERNAME  --password $PASSWORD --vcl-name $VCL_NAME --vcl-group $VCL_GROUP $FILENAME
```

Run `./dist/varnish-plus-cli.phar vac:deploy --help` for a full explanation of all arguments.

## Example Makefile

Note: Makefiles work with tabs, not spaces. When copying this example ensure the file is indented with tabs.

```makefile
DIST_DIR ?= dist
BIN_DIR ?= bin
TEMPLATE_DIR ?= templates

TEMPLATE_FILES = $(shell find $(TEMPLATE_DIR) -type f -name '*')

all: install compile

install: $(BIN_DIR) $(BIN_DIR)/varnish-plus-cli.phar

$(BIN_DIR):
	mkdir -p $(BIN_DIR)

$(BIN_DIR)/varnish-plus-cli.phar:
	wget https://github.com/liip/varnish-plus-cli/releases/latest/download/varnish-plus-cli.phar
	mv varnish-plus-cli.phar $(BIN_DIR)/
	chmod u+x $(BIN_DIR)/varnish-plus-cli.phar

# Add all possible VCLs you want to generate
compile: $(DIST_DIR)/dev.vcl $(DIST_DIR)/local.vcl $(DIST_DIR)/dev.maintenance.vcl

# maintenance is an example rule where you specify a custom twig variable which changes something in the VCL to
# indicate that the current node is in maintenance mode (could be anything, of course).
$(DIST_DIR)/%.maintenance.vcl: $(TEMPLATE_FILES)
	$(BIN_DIR)/varnish-plus-cli.phar vcl:twig:compile --twig-variable maintenance=true $(TEMPLATE_DIR) envs/$*.vcl.twig $@

# this assumes that there's the following directory layout:
#
# .
# ├── Makefile
# ├── templates
# │   └── envs
#     │   ├── dev.vcl.twig
#     │   ├── local.vcl.twig
$(DIST_DIR)/%.vcl: $(TEMPLATE_FILES)
	$(BIN_DIR)/varnish-plus-cli.phar vcl:twig:compile $(TEMPLATE_DIR) envs/$*.vcl.twig $@

.PHONY: install compile all
```

## Development

### Setup

```bash
$ git clone ...
$ composer install
```

### Create `.phar` file

```bash
$ make dist
```

### Code Quality

We use phpstan and php-cs-fixer for code style purposes:

```bash
$ make phpcs
# to fix code style issues automatically
$ make fix-cs
```
