# WP Snapshots

A project sharing tool for WordPress.

[![Support Level](https://img.shields.io/badge/support-active-green.svg)](#support-level) [![MIT License](https://img.shields.io/github/license/10up/wpsnapshots.svg)](https://github.com/10up/snapshots-command/blob/develop/LICENSE.md)

## Table of Contents  
* [Overview](#overview)
* [Installation](#install)
* [Authentication](#authentication)
* [Usage](#usage)
    * [configure](#configure)
    * [create-repository](#create-repository)
    * [push](#push)
    * [pull](#pull)
    * [search](#search)
    * [delete](#delete)
    * [create](#create)
    * [download](#download)
* [Identity Access Management](#identity-access-management)
* [Troubleshooting](#troubleshooting)
* [Support Level](#support-level)

## Overview

WP Snapshots is a project-sharing tool for WordPress. Operated via the command line with WP CLI, this tool empowers developers to easily push snapshots of projects into the cloud for sharing with team members. Team members can pull snapshots, either creating new WordPress development environments or into existing installs such that everything "just works." No more downloading files, matching WordPress versions, SQL dumps, fixing table prefixes, running search/replace commands, etc. WP Snapshots even works with multisite.

WP Snapshots stores snapshots in a centralized repository (AWS). Users set up WP Snapshots with their team's AWS credentials. Users can then push, pull, and search for snapshots. When a user pushes a snapshot, an instance of their current environment (`wp-content/`, database, etc.) is pushed to AWS and associated with a particular project slug. When a snapshot is pulled, files are pulled from the cloud either by creating a new WordPress install with the pulled database or by replacing `wp-content/` and intelligently merging the database. WP Snapshots will ensure your local version of WordPress matches the snapshot.

A snapshot can contain files, the database, or both. Snapshot files (`wp-content/`) and WordPress database tables are stored in Amazon S3. General snapshot meta data is stored in Amazon DynamoDB.

## Install

WP Snapshots is a WordPress plugin designed to be used in conjuction with WP CLI. An environment with WP CLI enabled is required, and it's highly recommended you run WP Snapshots from WITHIN your dev environment (inside VM or container). 

- TODO - WordPress plugin installation. Depends on build process and whether the repo is public.

## Authentication

WP Snapshots does not support authenticating with AWS by passing commands via the command line. Instead, you must set up your AWS credentials in your local environment. There are a few methods for authenticating; the easiest are below.

### 1. AWS Credentials File

Create a `~/.aws/credentials` file with the following contents:

```ini
[default]
aws_access_key_id = <your-access-key-id>
aws_secret_access_key = <your-secret-access-key>
```

Depending on your setup, you may need to create the `~/.aws` directory and the `credentials` file inside of your local environment. If you are using a VM or container, you may need to create the file in your local environment and then copy it into the VM or container. 

More information in [AWS documentation](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_credentials_profiles.html).

### 2. Environment Variables

Set the `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` environment variables. See the [AWS docs](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_credentials_environment.html) for more information.

## Usage

WP Snapshots revolves around pushing, pulling, and searching for snapshots. Any WordPress installation can be pushed. To pull, all you need is a working WordPress installation with this plugin installed and active.

Documentation for each operation is below.

### configure

WP Snapshots relies on AWS to store files and data. As such, you need to connect to a "repository" hosted on AWS. Each command supports a `--repository` argument to tell the script which AWS repository to use, but to bypass requiring that argument on every command, you can store the setting using the command below. After you have done so, WP Snapshots will use the first repository stored in your configuration by default.

#### Command

__wp wpsnapshots configure <repository> [--region=\<region\>] [--user_name=\<user_name\>] [--user_email=\<user_email\>]__

<details>
<summary>Show Arguments</summary>

  <repository>
    The name of the repository to configure.

  [--region=\<region\>]
    The AWS region to use. Defaults to us-west-1.
    ---
    default: us-west-1
    ---

  [--user_name=\<user_name\>]
    The username to use. If it's not provided, user will be prompted for it.

  [--user_email=\<user_email\>]
    The user email to use. If it's not provided, user will be prompted for it.
</details>

#### Examples

```
wp snapshots configure 10up
wp snapshots configure 10up --region=us-west-1 --user_name="John Doe" --user_email=john.doe@example.com
```

### create-repository

If WP Snapshots has not been set up for your team/company, you'll need to create the WP Snapshots repository. If a repository has already been created, this command will do nothing.

#### Command

__wp wpsnapshots create-repository <repository> [--region=\<region\>]__


<details>
<summary>Show Arguments</summary>

```
  <repository>
    The repository to create

  [--region=\<region\>]
    The AWS region to use. Defaults to us-west-1.
    ---
    default: us-west-1
    ---
```
</details>

### push

This command pushes a snapshot of a WordPress install to the repository. When finished, the command will return a snapshot ID that you can pass to team members. When pushing a snapshot, you can include files and/or the database.

WP Snapshots scrubs all user information including names, emails, and passwords.

Pushing a snapshot will not replace older snapshots with the same name. Each time you push, a new copy is created. Old snapshots no longer needed can be removed from AWS with the `delete` command (see below).

`--small` will take 250 posts from each post type along with the associated terms and post meta and delete the rest of the data. This will modify your local database, so be careful.

#### Command

__wp wpsnapshots push [--repository=\<repository\>] [--region=\<region\>] [--exclude=\<exclude\>] [--slug=\<slug\>] [--description=\<description\>] [--wp_version=\<wp_version\>] [--author_name=\<author_name\>]
  [--author_email=\<author_email\>] [--exclude_uploads] [--small] [--include_files] [--include_db]__

<details>
<summary>Show Arguments</summary>

```
  [--repository=\<repository\>]
    Repository to use.
    ---
    default: 10up
    ---

  [--region=\<region\>]
    The AWS region to use. Defaults to us-west-1.
    ---
    default: us-west-1
    ---

  [--exclude=\<exclude\>]
    Exclude a file or directory from the snapshot. Enter a comma-separated list of files or directories to exclude, relative to the WP content directory.
    ---
    default: ""
    ---

  [--slug=\<slug\>]
    Project slug for snapshot.
    ---
    default: ""
    ---

  [--description=\<description\>]
    Description of snapshot.
    ---
    default: ""
    ---

  [--wp_version=\<wp_version\>]
    Override the WordPress version.
    ---
    default: ""
    ---

  [--author_name=\<author_name\>]
    Snapshot creator name.
    ---
    default: ""
    ---

  [--author_email=\<author_email\>]
    Snapshot creator email.
    ---
    default: ""
    ---

  [--exclude_uploads]
    Exclude uploads from pushed snapshot.
    ---
    default: false
    ---

  [--small]
    Trim data and files to create a small snapshot. Note that this action will modify your local.
    ---
    default: false
    ---

  [--include_files]
    Include files in snapshot.
    ---
    default: true
    ---

  [--include_db]
    Include database in snapshot.
    ---
    default: true
    ---
```
</details>

### pull

This command pulls an existing snapshot from the repository into your current WordPress installation, replacing your database and/or `wp-content` directory entirely. The command will interactively prompt you to map URLs to be search and replaced. If the snapshot is a multisite, you will have to map URLs interactively for each blog in the network. This command will also (optionally) match your current version of WordPress with the snapshot.

After pulling, you can log in as admin with the user `wpsnapshots`, password `password`.

#### Command

__wp wpsnapshots pull <snapshot_id> [--repository=\<repository\>] [--region=\<region\>] [--skip_table_search_replace=\<skip_table_search_replace\>] [--site_mapping=\<site_mapping\>] [--main_domain=\<main_domain\>]
  [--confirm] [--confirm_wp_download] [--confirm_config_create] [--confirm_wp_version_change] [--confirm_ms_constant_update] [--overwrite_local_copy] [--include_files] [--include_db]__

<details>
<summary>Show Arguments</summary>

```
  <snapshot_id>
    Snapshot ID to pull.

  [--repository=\<repository\>]
    Repository to use.

  [--region=\<region\>]
    AWS region to use. Defaults to us-west-1.
    ---
    default: us-west-1
    ---

  [--skip_table_search_replace=\<skip_table_search_replace\>]
    Skip search and replacing specific tables. Enter a comma-separated list, leaving out the table prefix.
    ---
    default: ""
    ---

  [--site_mapping=\<site_mapping\>]
    JSON or path to site mapping file.

  [--main_domain=\<main_domain\>]
    Main domain for multisite snapshots.

  [--confirm]
    Confirm pull operation.
    ---
    default: false
    ---

  [--confirm_wp_download]
    Confirm WordPress download.
    ---
    default: false
    ---

  [--confirm_config_create]
    Confirm wp-config.php creation.
    ---
    default: false
    ---

  [--confirm_wp_version_change]
    Confirm WordPress version change.
    ---
    default: false
    ---

  [--confirm_ms_constant_update]
    Confirm multisite constant update.
    ---
    default: false
    ---

  [--overwrite_local_copy]
    Overwrite local copy of snapshot.
    ---
    default: false
    ---

  [--include_files]
    Include files in snapshot.
    ---
    default: false
    ---

  [--include_db]
    Include database in snapshot.
    ---
    default: false
    ---

```
</details>


### search

This command searches the repository for snapshots. `\<search_text\>` will be compared against project names and authors. Multiple queries can be used to search snapshots in different projects. Searching for "\*" will return all snapshots.

__wp wpsnapshots search <search_text> [--repository=\<repository\>] [--format=\<format\>] [--region=\<region\>]__

#### Command

<details>
<summary>Show Arguments</summary>

```
  <search_text>
    Text to search against snapshots. If multiple queries are used, they must match exactly to project names or snapshot ids.

  [--repository=\<repository\>]
    The repository to search in. Defaults to the first repository set in the config file.

  [--format=\<format\>]
    Render output in a particular format. Available options: table and json. Defaults to table.

  [--region=\<region\>]
    The AWS region to search in. Defaults to the first region set in the config file.
    ---
    default: us-west-1
    ---
```

</details>

### delete

This command deletes a snapshot from the remote repository. If you have the snapshot cached locally, your cached copy will not be deleted. (Local copies can be deleted manually within the `~/.wpsnapshots` directory.)

__wp wpsnapshots delete <snapshot_id> [--repository=\<repository\>] [--region=\<region\>]__

#### Command

<details>
<summary>Show Arguments</summary>

```
  <snapshot_id>
    Snapshot ID to pull.

  [--repository=\<repository\>]
    Repository to use.

  [--region=\<region\>]
    AWS region to use. Defaults to us-west-1.
    ---
    default: us-west-1
    ---
```
</details>

### create

The `create` command performs most of the same operations as `push`, but it does not push the generated files and/or database export to AWS. It creates a snapshot locally and stores it your the `~/.wpsnapshots` directory. It can subsequently be pulled into any local environment on your machine. This is useful if you want to create snapshots for your own development purposes but don't need to share with others.

#### Command

__wp wpsnapshots create [--repository=\<repository\>] [--region=\<region\>] [--exclude=\<exclude\>] [--slug=\<slug\>] [--description=\<description\>] [--wp_version=\<wp_version\>] [--author_name=\<author_name\>]
  [--author_email=\<author_email\>] [--exclude_uploads] [--small] [--include_files] [--include_db]__

<details>
<summary>Show Arguments</summary>

```
  [--repository=\<repository\>]
    Repository to use.
    ---
    default: 10up
    ---

  [--region=\<region\>]
    The AWS region to use. Defaults to us-west-1.
    ---
    default: us-west-1
    ---

  [--exclude=\<exclude\>]
    Exclude a file or directory from the snapshot. Enter a comma-separated list of files or directories to exclude, relative to the WP content directory.
    ---
    default: ""
    ---

  [--slug=\<slug\>]
    Project slug for snapshot.
    ---
    default: ""
    ---

  [--description=\<description\>]
    Description of snapshot.
    ---
    default: ""
    ---

  [--wp_version=\<wp_version\>]
    Override the WordPress version.
    ---
    default: ""
    ---

  [--author_name=\<author_name\>]
    Snapshot creator name.
    ---
    default: ""
    ---

  [--author_email=\<author_email\>]
    Snapshot creator email.
    ---
    default: ""
    ---

  [--exclude_uploads]
    Exclude uploads from pushed snapshot.
    ---
    default: false
    ---

  [--small]
    Trim data and files to create a small snapshot. Note that this action will modify your local.
    ---
    default: false
    ---

  [--include_files]
    Include files in snapshot.
    ---
    default: true
    ---

  [--include_db]
    Include database in snapshot.
    ---
    default: true
    ---
```
</details>

### download

This command downloads a snapshot from the remote repository and stores it in the `~/.wpsnapshots` directory. It will not pull the data or files into your local environment. If you subsequently run the `pull` command with the downloaded snapshot ID, the data and/or files will be pulled into your local environment without requiring a new download.

#### Command

__wp wpsnapshots download <snapshot_id> [--repository=\<repository\>] [--region=\<region\>] [--include_files] [--include_db]__

<details>
<summary>Show Arguments</summary>

```

  <snapshot_id>
    Snapshot ID to download.

  [--repository=\<repository\>]
    Repository to use.

  [--region=\<region\>]
    AWS region to use. Defaults to us-west-1.
    ---
    default: us-west-1
    ---

  [--include_files]
    Include files in the download.
    ---
    default: true
    ---

  [--include_db]
    Include database in the download.
    ---
    default: true
    ---
```
</details>


## Identity Access Management

WP Snapshots relies on AWS for access management. Each snapshot is associated with a project slug. Using AWS IAM, specific users can be restricted to specific projects.

## Troubleshooting

* __wpsnapshots push or pull is crashing.__

  A fatal error is most likely occuring when bootstrapping WordPress. Look at your error log to see what's happening. Often this happens because of a missing PHP class (Memcached) which is a result of not running WP Snapshots inside your environment (container or VM).

## Support Level

**Active:** 10up is actively working on this, and we expect to continue work for the foreseeable future including keeping tested up to the most recent version of WordPress.  Bug reports, feature requests, questions, and pull requests are welcome.

## Like what you see?

\<a href="http://10up.com/contact/"\>\<img src="https://10up.com/uploads/2016/10/10up-Github-Banner.png" width="850" alt="Work with us at 10up"\>\</a\>