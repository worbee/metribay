# Security

Metribay stores API tokens and service account keys for other services. I take reports about it seriously.

## Reporting a problem

Email **rob@worbee.com**. Please don't open a public issue, pull request or discussion for a security problem.

Tell me:

- what the problem is and where it is
- how to reproduce it
- what an attacker could do with it
- the version or commit you tested

I'll confirm I got your report, keep you posted while I work on a fix and credit you in the release notes if you want. Please give me a reasonable time to ship the fix before you publish anything.

## Supported versions

Fixes go into the latest release. Self-hosted installs should stay on it.

## What's in scope

This repository: the `metribay/core` package, the standalone application and the Docker image. Metribay Cloud at app.metribay.com runs the same package, so report problems you find in it here too. Please don't test against other people's data or run automated scanners against Cloud.

Problems in a dependency go to that project. Tell me as well if Metribay is affected.
