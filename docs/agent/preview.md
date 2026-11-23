---
title: The Preview in the editor, and how to fix it
read-when: the Preview is broken, blank, stale or shows an error; before touching the dev server, its port, a generated file or a half-done merge
---
## How the Preview runs
- The editor runs the dev server, not you: `npm run dev` for an Astro site, a plain file server (`wrangler dev`)
  for a site with no build. It has ONE fixed port: Astro 4321, a Static site 8788 (TanStack Start 3000),
  or the port the project's own config names. It never moves to another port.
- Every Preview start or restart first stops whatever holds that port (its own old server, or one you or a
  terminal started) and writes what it stopped to the log. A dev server of yours is stopped; a second one
  never helps. Never pass `--port`, never change the port in the config to get around a busy one.
- The log is `{{PREVIEW_LOG}}`: install output, dev server output and the editor's own notes. The owner sees
  its last lines in the Preview pane.
- A changed `package-lock.json` restarts the Preview by itself once installs have finished.

## `{{PREVIEW_COMMAND}}`: see and restart the Preview
| Command | What it does |
| --- | --- |
| `{{PREVIEW_COMMAND}} status` | state, port, ready, last message. Exit 0: running; 1: not running or crashed; 2: the editor did not answer |
| `{{PREVIEW_COMMAND}} logs [-n N]` | the last N lines of `{{PREVIEW_LOG}}` (default 80) |
| `{{PREVIEW_COMMAND}} restart` | the owner's Restart button: stops the whole dev server, takes the port back, starts clean, prints the new state |

- Restart with `{{PREVIEW_COMMAND}} restart`, never with `kill`: a killed server counts as a crash, and after a few
  crashes in a row the editor stops trying.
- Exit 2: the editor is restarting itself. Wait 10 seconds and ask again; still 2, tell the owner.
- No `{{PREVIEW_COMMAND}}` command (an older editor): read `tail -n 80 {{PREVIEW_LOG}}` and ask the owner to press
  Restart in the Preview.

## Fix a broken Preview, in this order
1. `{{PREVIEW_COMMAND}} status`. Running and ready, but the page shows an error: it is the code. Read
   `{{PREVIEW_COMMAND}} logs -n 120` and the error on the page, fix the code; the dev server reloads by itself.
2. `git status`. "You have unmerged paths" or files marked `UU`: the project is mid-merge. Fix that first
   ("A merge left half done" below): nothing else works until it is done.
3. `{{PREVIEW_COMMAND}} logs -n 120`, and match the last error:

| The log says | Cause | Fix |
| --- | --- | --- |
| `already in use`, `EADDRINUSE`, the editor stopped a process on the port | port taken | `{{PREVIEW_COMMAND}} restart` (it takes the port back); never another port |
| `Cannot find module` or `Cannot find package` naming a package, `ERR_MODULE_NOT_FOUND`, `command not found` | missing packages | `npm install` (nothing else heavy at the same time), then `{{PREVIEW_COMMAND}} restart` if status is not running |
| status `crashed`, a stack trace, `exited with code` | the server crashed | fix the error the log names (often the file you changed last), then `{{PREVIEW_COMMAND}} restart` once |
| a type or module missing from `.astro/`, a content collection that stays empty | stale generated file | never edit it: `{{PREVIEW_COMMAND}} restart` (Astro writes `.astro/` again at start) |
| `<<<<<<<`, `Unexpected token '<<'`, `Merge conflict marker` | merge markers | "A merge left half done" below |

4. After each fix: `{{PREVIEW_COMMAND}} status` until it exits 0, then open the page in the Preview.
5. Two restarts and still broken: stop. Tell the owner in plain words what the log says (its last error
   line) and what you tried. Never loop restarts.

## A merge left half done
A platform import ("bring in new source") can stop half way when the new source and this working copy
changed the same lines. Check `git status` at the start of a session, every session.
- `git diff --name-only --diff-filter=U` names the files; `grep -rln '^<<<<<<< ' src` finds left-over markers.
- In each file keep the right content from both sides and remove the `<<<<<<<`, `=======` and `>>>>>>>` lines.
  Never merge `package-lock.json` by hand: `git checkout --theirs package-lock.json`, then `npm install`.
- Then `git add` those files and `git commit` to finish the merge, and `{{PREVIEW_COMMAND}} restart`.
- You can't tell which side the owner wants: ask them before you commit.

## Generated files: never edit by hand
`.astro/` (Astro's types), `dist/` (the build) and, in an imported project, `src/routeTree.gen.ts` (TanStack
Router). Change what they are made from and let them be made again.

## Git and `origin`
`origin` is the platform's git proxy, not GitHub. `git push` works only while the owner's editor session is
open; after it ended, a push answers 403. That is not a password problem: never change the remote, add a
token or ask for one. Commit, and tell the owner the push waits for the next Open.

## Reopening the editor
Settings the owner changes reach the Preview only at the next Open of the editor, and that Open stops every
process of the sandbox user: the dev server, terminals and your own commands. Commit your work first, then ask
the owner to close and open the editor.
