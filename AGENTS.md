# AGENTS.md

This file governs all design, development, testing, review, and GitHub collaboration activities in this repository. Read it before starting work. If a subdirectory contains a more specific `AGENTS.md`, follow that file within its scope.

## 1. Project Positioning

**Universal Markdown Editor** is an independent, general-purpose, publicly maintained WordPress plugin that gives users who prefer Markdown source writing a Gutenberg editing experience.

The first version aims to provide Markdown source editing, live preview, a common formatting toolbar, and WordPress media library insertion on the post editing screen. It must also preserve editable Markdown source and standard HTML that themes can render directly.

Product boundaries that must not be relaxed:

- Do not depend on 2BLOG, another specific theme, LeanCloud, private APIs, or site-specific resources.
- Front-end content must use the standard WordPress `post_content` field. Regular themes must not require special adaptation.
- Store Markdown source in plugin-private post meta. Final field names, empty-value semantics, REST visibility, conversion versions, and migration strategy are governed by the architecture decision in #2. Do not treat example fields as an established contract before that decision is accepted.
- The server owns trusted parsing and security filtering. Browser previews must never be used directly as persisted HTML.
- Never overwrite existing posts without warning. New posts, updates, autosaves, mode switches, failure recovery, deactivation, and uninstallation must all protect user content.
- Save endpoints must verify authentication, specific post or media permissions, and nonces. Private Markdown must not be exposed to unauthorized visitors.
- Code highlighting must be configurable. By default, avoid duplicating theme-level code highlighting.

The first version explicitly excludes classic editor support, AI writing, multi-user collaboration, cloud synchronization, replacement of all Gutenberg blocks, theme-specific shortcodes, and a formal WordPress.org release. Any new scope must first update the product design and relevant Issue. Do not expand scope silently during implementation.

## 2. Sources of Truth and Current Phase

Resolve requirements using the following priority order. If sources conflict, report the conflict and clarify it instead of choosing the most convenient interpretation:

1. Explicit user requirements and confirmed decisions in the current task.
2. Accepted Proposals, architecture documents, and ADRs.
3. Goals, non-goals, dependencies, and acceptance criteria of the relevant Issue.
4. Existing repository code, tests, documentation, and engineering configuration.
5. The general workflow in this file and the referenced skills.

Core project references:

- #1, "Define the first universal Markdown editor MVP": source of product scope and acceptance criteria.
- #2, "Define the plugin architecture, editing modes, and data contract": source of module boundaries, state flow, data contract, and security boundaries.
- #3 -> #4 -> #5 -> #6 -> #7 -> #8: current planned implementation dependency order. Work that crosses this order must first confirm that its dependencies are satisfied.
- `docs/architecture.md` and `docs/adr/`: accepted sources of architectural truth.

The product direction in #1 and the architecture in #2 have been explicitly accepted by the user. Subsequent implementation follows the dependency order #3 -> #4 -> #5 -> #6 -> #7 -> #8. **The current phase must not be hard-coded in this file.** Before starting a task, verify the latest `origin/main`, GitHub Issue and PR states, and the actual working branch. A created or pushed PR is not a merged PR. Start work that depends on a prior PR only after that PR has merged and the related Issue is complete.

The repository is public. Do not write secrets, credentials, real user data, private site addresses, production posts, or unauthorized third-party code into code, documentation, Issues, PRs, logs, test fixtures, or commit history.

## 3. Working Approach and Skills

This is a publicly maintained, multi-iteration project maintained by an individual. Use a lightweight team workflow: preserve the traceable chain of `Proposal/ADR -> Issue -> short-lived branch -> Commit -> PR -> Review/CI -> Merge -> Release`, without imposing CODEOWNERS, DORA metrics, or governance overhead that does not match the project's current scale.

Read and follow the relevant local skill for each task. Do not infer its contents from the filename:

- Product and scope: `/Users/yang/.codex/skills/product-design/SKILL.md`
- GitHub delivery: `/Users/yang/.cc-switch/skills/github-engineering-workflow/SKILL.md`
- Implementation and bug fixes: `/Users/yang/.codex/skills/code-implementation/SKILL.md`
- Test design and verification: `/Users/yang/.codex/skills/code-testing/SKILL.md`
- Code review: `/Users/yang/.codex/skills/code-review/SKILL.md`
- README creation or modification: `/Users/yang/.codex/skills/project-readme/SKILL.md`

These standards have distinct responsibilities. The product skill determines what to build; the engineering skill governs the delivery chain; the implementation skill owns TDD; the testing skill owns verification evidence; and the review skill independently closes out review. Passing tests does not mean Review, CI, or release gates have passed.

Execution principles:

- Confirm the task, acceptance criteria, non-goals, authorization scope, and risk before making changes.
- Deliver one independently verifiable increment at a time. Do not refactor opportunistically or introduce unrelated formatting noise.
- Prefer WordPress platform capabilities or mature, maintained dependencies with compatible licenses over reinventing functionality. Before adding a dependency, confirm its source, version, license, security posture, and packaging method through an architecture decision.
- When the plan conflicts with reality, distinguish a planning error from an implementation error. Record the deviation and its rationale in the relevant PR, Issue, or a later design decision. Do not change the contract privately.
- If a tool is unavailable, state that fact and use a feasible alternative verification method. Never claim to have used a tool, environment, or check that does not exist or was not used.
- Respect existing workspace changes. Before editing, inspect `git status --short --branch`. Do not overwrite, revert, or commit unrelated changes made by the user or others. Do not commit `.DS_Store`.
- Continuous development does not justify skipping gates. After pushing a PR, monitor and fix CI. Before human Review and merge, do not stack dependent work on the same branch and do not merge the PR yourself.

## 4. Design and Architecture Gates

For design tasks, first write back your understanding and separate "explicitly stated by the user" from "assumptions requiring confirmation." Mark unknown information as TBD. Do not fabricate compatibility, version, performance, or competitor conclusions.

Every Proposal must present alternatives, the selected option and rationale, explicit non-goals, boundaries, examples, and acceptance criteria. For UI work, define textual behavior before producing a prototype based on confirmed wording. Treat finalized designs as read-only history: add revisions or a follow-up Proposal when requirements change. Do not overwrite earlier judgments. Append a new ADR when technical decisions change.

Implementation must not begin until #2 has fixed at least the following:

- The Gutenberg integration method and the rationale for preserving native block data.
- State flows for editing, preview, saving, mode switching, conflicts, and failure recovery.
- The data contract for Markdown source, HTML, source mode, and conversion version.
- Handling strategy for new posts, existing Gutenberg posts, and existing classic HTML posts.
- The consistency strategy between server-side and browser parsing.
- Supported PHP and WordPress ranges, dependencies, licenses, and packaging method.
- HTML allowlist, dangerous URLs, permissions, nonces, and private meta boundaries.
- Deactivation, uninstallation, conversion failure, recovery, and migration strategy.

Architecture documentation must describe actual module responsibilities, interfaces, and dependency directions. A directory tree or technology list does not substitute for architecture.

## 5. Issue, Branch, and PR Collaboration

### Definition of Ready

The following conditions must be met before implementation begins:

- A user-accepted product and architecture basis exists, and the relevant Issue has clear scope and acceptance criteria.
- Prerequisite Issues are complete, or interface contracts that permit parallel work have been explicitly documented.
- The Issue includes background, goals, non-goals, relationships, risk and rationale, subtasks, and a verification plan.
- The working branch, current uncommitted changes, public-information boundary, and available verification methods have been confirmed.
- GitHub has been used to verify the actual state of prerequisite Issues and PRs. An open PR, a closed-but-unmerged PR, or a local commit alone does not count as merged.
- Risk labels match the risk assessment in the body. Resolve or clarify any mismatch before beginning implementation.

If readiness is not satisfied, continue designing or clarifying. Do not write production code.

### GitHub Metadata

- **All GitHub Issue and pull-request titles and bodies must be written in English.** Branch names and commit titles must also be in English. Preserve code identifiers, labels, URLs, version numbers, and exact quotations from external sources in their original form when required; translate the surrounding explanation into English.
- Continue using the repository's existing type and domain labels, including `enhancement`, `architecture`, `infrastructure`, `documentation`, `security`, and `testing`.
- Every Issue and PR must have at least one applicable type or domain label and exactly one of `risk:low`, `risk:standard`, `risk:high`, or `risk:emergency`. The body must state the risk rationale.
- `low`: documentation, tests, or mechanical changes that do not alter runtime behavior.
- `standard`: a well-bounded, independently reversible general feature or fix.
- `high`: permissions, privacy, XSS, public interfaces, data persistence or migration, dependency supply chain, release artifacts, or a similarly large blast radius.
- `emergency`: restricted to an active service or security incident and requires explicit user authorization. Complete the normal gates and incident review afterward.
- Risk labels do not replace the rationale in the body, and the project being maintained by an individual is not a reason to lower security or data risk.
- Organize Milestones around verifiable iterations. Do not confuse them with Git Tags, Labels, or Issue states. Release Tags must be immutable `vMAJOR.MINOR.PATCH` values.

### Branches and Commits

- The default branch is `main`. Never push or commit directly to `main`. Deliver through short-lived branches and PRs.
- Name branches `<type>/<issue>-<slug>`, for example `feat/5-markdown-editor` or `docs/2-architecture`. Use lowercase English letters, numbers, and hyphens. One branch serves one Issue goal.
- Before creating a new branch, run `git fetch origin --prune` and create it from the latest `origin/main` while the worktree is clean. Before reusing a current branch, confirm that it belongs to the current task. Do not change the semantic purpose of an existing branch, and do not mix collaboration documentation or unrelated fixes into an unmerged feature PR.
- Use Conventional Commits: `<type>(<scope>): <summary>`. Branch names, commit titles, Issue titles and bodies, and PR titles and bodies must be in English.
- Each commit must express one complete intent and must not include unrelated files. Recheck the diff and verification results before committing, and stage only explicitly listed files. Local or generated content such as `.DS_Store`, `.codex/`, `dist/`, `build/`, `vendor/`, and `node_modules/` must not enter commits.
- Push, merge, close Issues, create Releases, or change remote repository settings require authorization for the current task. Do not treat local completion as remote delivery.

### PRs and Review

- Connect a PR to its Issue with `Closes #<issue>` or another appropriate linking phrase. Do not accidentally close an Issue for a staged design draft.
- The PR body must explain the problem, key changes, risk and rationale, verification evidence, compatibility, data impact, deactivation or rollback method, and uncovered items. Apply the appropriate labels.
- Before submitting a PR, complete a local diff self-review, then have the user or a suitable human reviewer review it. By default, do not publish a "self-review" comment on the PR. Publish a comment only when the user requests it or a review issue must be discussed publicly. AI self-review must not impersonate human approval, and you must not approve or merge your own PR.
- Review must pin the base and head plus the source of requirements, then examine standards compliance and requirements compliance separately. Use `blocking`, `question`, `nit`, and `fyi` classifications. Only `blocking` findings prevent merge.
- Merge only after Review approval and required CI success, performed by the user or someone with explicit authorization. After merge, confirm main-branch checks and clean up the merged short-lived branch according to the next section.

### Post-Merge Branch Cleanup

Cleanup removes branch references, not PRs, Issues, or merge commits. After every merge, follow this order:

1. Run `gh pr view <number> --json state,mergedAt,headRefName` and confirm `state=MERGED`, a non-null `mergedAt`, and the branch name. Always retain branches for open or closed-but-unmerged PRs. Never infer that a PR was merged merely because its branch was pushed, CI passed, or a local commit with the same name exists.
2. Confirm the worktree is clean and the current branch is not the one to delete, then update the default branch:

   ```sh
   git switch main
   git pull --ff-only origin main
   ```

3. Confirm that required checks on the merged default branch have started and eventually pass. If the default branch fails, report and fix it immediately. CI success before the PR merge does not establish default-branch health.
4. Delete the remote and local short-lived branch for the merged PR:

   ```sh
   git push origin --delete <branch>
   git branch -d <branch>
   git fetch origin --prune
   ```

   Squash or rebase merges may prevent `git branch -d` from recognizing the branch as an ancestor. In that case, use `git branch -D` only on a branch used exclusively by that PR after confirming the PR was merged and equivalent changes are in `main`. If merge status or unique changes cannot be confirmed, retain the branch and report it.
5. Recheck with `git branch -vv`, `git branch -r`, and the open PR list. A missing remote ref may be treated as successfully cleaned before continuing with other branches, but do not stop and leave other merged branches behind. Never delete the default branch or branches belonging to unmerged PRs.

## 6. Implementation and Testing Requirements

All plugin code that enters `main` is considered **maintained** and defaults to the full intensity of the `code-implementation` skill:

1. Derive a small-step implementation plan, affected modules, and verification methods from the acceptance criteria.
2. For each behavior change, first write a failing test that distinguishes the correct implementation from an incorrect one. Confirm that the failure occurs for the expected reason.
3. Write the minimum implementation required to pass, then refactor and retest. Preserve red-to-green evidence.
4. For bug fixes, first reproduce and identify the root cause, then fix it and add a regression test. Do not try multiple unrelated fixes at once.
5. Finally, run the affected tests, the repository's required full test suite, linting, builds, and security checks, then verify every acceptance criterion.

Tests must derive expectations from product and architecture contracts. They must not call the implementation under test to generate expected values. Cover normal input, empty values, boundaries, errors, repeated operations, permission failures, and side effects. Do not test only the happy path.

Use `package.json`, `composer.json`, `.wp-env.json`, and CI configuration as the source of truth for commands. Record actual commands, working directories, exit codes, test counts, failures, skips, and environment limitations. When native PHP is unavailable locally, use the repository's pinned Docker Composer and PHP entry points. Without a real WordPress environment, report only the static or other checks that actually ran; do not claim WordPress integration, installation, or compatibility verification.

## 7. Security and Data Protection Checks

When work affects #4, #6, #7, or release artifacts, verify at least the following:

- `<script>`, event-handler attributes, `javascript:`, dangerous `data:` URLs, and disallowed HTML cannot enter trusted output.
- Unauthorized REST or AJAX requests cannot read private source, modify posts, or upload media.
- Saving, autosaving, and repeated saves cannot create inconsistencies between Markdown and `post_content`.
- Parsing or save failures cannot write partial output or damage previous Markdown and HTML.
- Existing Gutenberg or classic HTML content receives an explicit warning before conversion and can be canceled or restored.
- The toolbar preserves selected text. Media insertion does not persist blob URLs. Cancellation and upload failures do not damage source.
- After deactivation, standard HTML remains readable. By default, uninstallation does not delete post content or source meta.
- There is real verification evidence for display in the default theme and at least one non-2BLOG third-party theme, plus editor operation at desktop and narrow-screen sizes.

Do not obtain green results by deleting assertions, weakening security expectations, or adding skips and retries. Tests may use fictional content and dedicated test environments only, never real site credentials or production posts.

## 8. Definition of Done and Delivery Reporting

An Issue may be considered complete only when all of the following conditions are met:

- Every in-scope acceptance criterion has code, documentation, or real verification evidence. Unfinished items remain explicitly open.
- Tests are valid and were rerun after the final change. Required checks have no failures or unexpected skips.
- Security, compatibility, data protection, and recovery requirements have been verified according to their risk.
- Documentation and architectural decisions are synchronized. Design changes use appended records.
- The diff contains no debug residue, unrelated files, sensitive information, or unreviewed dependencies.
- The PR is linked correctly, and human Review and required CI are complete. Local success does not impersonate remote success.
- After the PR merge, default-branch health has been confirmed and the corresponding local and remote short-lived branches have been cleaned up. An unmerged PR is only a delivery candidate awaiting review; it does not mean the Issue is complete.
- For a Release, #8 is also complete, installation has been verified in a real clean WordPress environment, and there is a traceable ZIP, source commit, SHA-256 digest, known-limitations section, upgrade procedure, and rollback procedure. Do not create a formal `v0.1.0` Release before these conditions are met.

For each delivery, report concisely:

1. The current Issue or phase, the files actually changed, and the acceptance criteria they satisfy.
2. The risk level and rationale.
3. The real commands and results for red/green tests and final verification, plus tests not run, environment limitations, and residual risks.
4. The actual states and links for branch, commit, PR, CI, Review, and Release. Explicitly state when any of these do not exist.
5. The next smallest executable action and whether user design acceptance or remote-operation authorization is still required.

Do not use unsupported statements such as "it should pass" or "the full workflow is complete." When blocked, preserve the worktree and evidence, describe the concrete blocker and the minimum input required, and do not bypass gates.
