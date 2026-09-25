# Command Net Discord

A [forumify](https://forumify.net) plugin bridging the Command Net suite to Discord —
across as many Discord servers as your community has, not just one. Every unit can get
its own private server with its own role mapping and its own notification channels,
instead of everyone sharing one community-wide bot connection.

Built for a specific MILSIM community's forumify install, using forumify's own
[forumify-discord-plugin](https://github.com/forumify/forumify-discord-plugin) as
reference material while building this from scratch; not a general-purpose skeleton, and
not a redistribution of that plugin.

## Requirements

- PHP 8.4 or newer
- A forumify 1.2.x install
- MySQL (for the migration in `migrations/`)
- [`commandnet-discord-bot`](https://github.com/Spearhead-Gaming/commandnet-discord-bot)
  running somewhere reachable from this install — this plugin talks to it over HTTP, it
  doesn't run a Discord gateway connection itself

## Install

```bash
composer require majesticdev/commandnet-discord-plugin
```

Then, from the forumify install:

```bash
bin/console forumify:plugins:refresh
bin/console forumify:plugins:activate majesticdev/commandnet-discord-plugin
bin/console doctrine:migrations:migrate
```

## Entities

| Entity | Notes |
| --- | --- |
| `DiscordConnection` | One Discord server the bot has been invited to — the community server, or a unit's private one. Guild ID, invite link, ops-log/announcements channel IDs, active flag. |
| `DiscordRoleMapping` | "Grant/revoke this Discord role whenever a user gains/loses this forumify Role" — scoped to one `DiscordConnection`, so the same forumify Role can map to a different Discord role in each unit's server. |

## Admin

- **Admin → Command Net Discord → Connections** — CRUD for `DiscordConnection`, with an
  embedded, add/remove-able role-mapping table per connection.
- **Admin → Command Net Discord → Settings** — bot-wide toggles only (force-connect
  Discord account, force users to join the server, sync display names, calendar
  cross-posting). Invite links and role mapping used to live here too; they moved to
  per-connection once a single server stopped being a safe assumption.

## Permissions

Checked as `discord.<area>.<action>`.

| Permission | Grants |
| --- | --- |
| `discord.admin.connections.view` / `.manage` | View / create, edit, and delete Discord connections and their role mappings. |

## Importing Discord members

Forumify's built-in `user` role only applies to accounts that exist, so a Discord member who has
never logged in has no permissions. To create accounts up front:

```bash
php bin/console discord:members:import          # dry run: reports what it would do
php bin/console discord:members:import --apply  # creates the accounts
```

For every human member of every active connection's guild it creates a password-less, email-less
account linked to their Discord id. When they later log in with Discord, forumify finds that linked
account and reuses it. Members who are already linked are left alone. Requires a bot that supports
`GET /data?type=guildMembers` (commandnet-discord-bot).

Members whose Discord username or display name equals an existing forum username are **skipped and
listed**, because a placeholder linked next to their real account would leave them with two once they
connect Discord. Re-run the command after new people join the server.

### Avoiding duplicate accounts

- `discord:members:import --match` links a member to the forum account whose username **exactly**
  equals their Discord *username* (display names are never used, since anyone can set theirs to
  anything). Run it without `--apply` first and read the list.
- `discord:members:link <forum-username> <discord-id> [--apply]` does the same for one person, for
  cases the exact match misses (Discord ids are visible with Developer Mode on).
- If an imported placeholder already holds that Discord id, it is deleted and the id moves to the
  real account, but only when the placeholder is untouched: no email, never active, no roles.
  Otherwise nothing changes and the command says why. An account that already has a different
  Discord link is left alone too.
- Exact name matching can still be wrong if a different person holds that name on Discord, so
  review the dry run before `--apply`.

## Design notes

- **One bot, many connections.** [`commandnet-discord-bot`](https://github.com/Spearhead-Gaming/commandnet-discord-bot)
  is a single Discord application invited into every server; this plugin never assumes a
  guild ID, it always asks "which connections apply" and loops.
- **`BotService::updateRoles()`/`updateUsername()` walk every active connection**
  themselves rather than being told which one — callers (`UpdateUserListener`, etc.) never
  changed when this went from single-server to multi-server.
- **`BotService::postAnnouncement()`** is the generic "tell every server's announcements
  channel something happened" primitive. `CalendarEventListener` uses it for calendar
  cross-posting today; it's the seam future plugins (id-card, a server manager, S3 tools)
  are meant to build on instead of talking to the bot directly.
- **`PatrolAnnouncementListener`** cross-posts a Command Net patrol the same way, on its
  `Operation` postPersist rather than a calendar event, so a patrol announces once whether
  it's posted from the web or from `/command-net-patrol-create`. `DiscordPatrolReminderNotifier`
  decorates Command Net's own `PatrolReminderNotifier` (a no-op there without this plugin)
  to also echo the AAR due/overdue reminder to Discord, `@mention`-ing the leader. Both
  reference `MajesticDev\CommandNet\*` classes the same un-required way `CalendarEventListener`
  references the calendar plugin's - see phpstan.neon's matching ignore rule.
- **Discord role IDs are pasted, not fetched live.** `DiscordRoleMappingType` uses a plain
  text field with instructions (enable Developer Mode, right-click the role, copy ID)
  rather than a live dropdown from the bot — editing a mapping doesn't require the bot to
  be online.

## Known gaps

- **No automated test coverage.** The `tests/` harness boots (CI runs it green), but
  `tests/Tests/` only has the kernel bootstrap — no actual test classes exist yet.
- **`commandnet-discord-bot` self-registers via `POST /api/discord/register-bot`** - the
  `/api` prefix comes from `api_platform`'s routing config (every `ApiResource`, including
  this plugin's, lives under it), not from anything in this plugin's own code, so it's easy
  to miss when reading `DiscordRegistration`'s `uriTemplate` alone.
- **The connections form's role-mapping table has no add/remove UI wired up.**
  `assets/dist/settings_form_controller.js` used to power exactly this for the old
  single-connection settings form; it's currently unused rather than repointed at the new
  per-connection form.
- **`JoinServerController`'s default connection is "whichever was created first"** (no
  explicit "this is the community server" flag) — fine with one extra connection, would
  want a real default marker once several units have their own.

## Works well with

- [`commandnet-plugin`](https://github.com/Spearhead-Gaming/commandnet-plugin) — `Unit`
  carries an optional `discordGuildId` for future per-unit notifications (AWOL alerts,
  operation posts), matched against a `DiscordConnection` here by guild ID.
- [`commandnet-discord-bot`](https://github.com/Spearhead-Gaming/commandnet-discord-bot) —
  the bot process this plugin talks to. See its README for the full HTTP contract.
- [`forumify-id-card-plugin`](https://github.com/MajesticDevBox/forumify-id-card-plugin) —
  reaches into `Forumify\Discord`'s (now `MajesticDev\Discord`'s) command interface for its
  own Discord slash commands the same way `commandnet-plugin` does.
