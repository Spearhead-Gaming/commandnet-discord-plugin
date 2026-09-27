# commandnet-discord-plugin

A [Forumify](https://forumify.net) plugin (`majesticdev/commandnet-discord-plugin`) bridging
the Command Net suite to Discord — across as many Discord servers as the community has, not
just one. Every unit can get its own private server with its own role mapping and
notification channels. Written from scratch using forumify's own
[forumify-discord-plugin](https://github.com/forumify/forumify-discord-plugin) as reference,
not a redistribution of it. See [README.md](README.md) for the entity/permission tables and
design notes.

This plugin **talks to a Discord bot over HTTP**; it does not run a Discord gateway
connection itself. See `commandnet-discord-bot`.

Built for this one community's Forumify install, not a general-purpose skeleton.

## The ecosystem

Sibling repos under `G:\Github Repos`: **commandnet-plugin** (the role/user data this plugin
syncs to Discord comes from here), **commandnet-s3-plugin** (optional consumer — its
Discord announcers only load when this plugin is present), **commandnet-discord-bot**
(required at runtime — the actual Discord gateway process this plugin's `BotService` calls
over HTTP), **command-net-theme**, **milsim-id-card-plugin**.

**One bot, many connections** is the core design: `DiscordConnection` is one guild the bot has
been invited to (community server, or a unit's private one); `BotService::updateRoles()` /
`updateUsername()` / `postAnnouncement()` walk every *active* connection themselves rather
than being told which one, so callers never need to know how many servers exist.
`postAnnouncement()` is the generic "tell every server's announcements channel" primitive —
new plugins wanting to post to Discord should build on that rather than talking to the bot
directly.

## Local dev

Developed via a Composer **path repository**, not a packaged install — the real Forumify app
is `~/dev/forumify` in WSL, whose `composer.json` points a `path` repo at this directory's WSL
path (`/mnt/g/Github Repos/commandnet-discord-plugin`). Editing here edits the live plugin.

For this plugin's Discord features to actually do anything in dev, `commandnet-discord-bot`
needs to be running and reachable, and registered via `POST /api/discord/register-bot` (note
the `/api` prefix - that's `api_platform`'s routing config, not this plugin's own; see that
repo's README for the handshake). Without the bot reachable, this plugin degrades to no-op
(role sync, announcements, slash commands simply don't fire) — the rest of the Forumify app
keeps working.

After a path-repo/composer change from the app root:
```bash
bin/console forumify:plugins:refresh
bin/console forumify:plugins:activate majesticdev/commandnet-discord-plugin
bin/console doctrine:migrations:migrate
```

## Commands

```bash
make quality        # phpcs (strict) + phpstan
make quality-fix     # phpcbf autofix
make tests           # setup-tests + run-tests
make setup-tests      # rebuilds tests/var DB, migrates, activates this plugin under test
make run-tests         # phpunit only, against the already-set-up test DB
```
`tests/` is a self-contained Forumify test kernel with its own `composer.json` — not run
against the dev app in `~/dev/forumify`. Per the README's Known gaps: the test harness boots
and CI runs it green, but `tests/Tests/` only has the kernel bootstrap — no real test classes
exist yet.

## Gotchas learned the hard way

- **Discord role IDs are pasted, not fetched live** (`DiscordRoleMappingType` is a plain text
  field with copy-the-ID instructions) — this is deliberate, so editing a mapping never
  requires the bot to be online. Don't "fix" this into a live dropdown without checking why.
- The bot's own README currently describes the PHP side as a "known gap" (assumes one guild,
  no `DiscordConnection`) — that's **stale**; this plugin's multi-guild `DiscordConnection`
  model is already built. Don't take that note in `commandnet-discord-bot/README.md` at face
  value without checking this plugin's actual code first.
- Same Docker-dev-stack `APP_DEBUG`/path-repo-over-Windows-drive slowness applies here as in
  the sibling plugins — see `commandnet-plugin/CLAUDE.md` Gotchas for the details, they're not
  repeated per-repo.
