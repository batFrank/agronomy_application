# 🤖 Project Agents

> Stack: Symfony 7.3 backend (DDD/CQRS) + **Flutter** frontend. Use the `flutter` skill for anything under `frontend/`.

This document describes all available agents and how to spawn them.

## Available Agents

### 1. Product Owner Agent

**Purpose**: Define epics, issues, and tasks with clear business value and acceptance criteria

**Skills**: 
- `product-owner` - Complete workflow for epic/issue/task definition

**How to spawn**:
```
Agent({
  description: "Product Owner agent for issue and epic definition",
  subagent_type: "product-owner",
  prompt: "Load the product-owner skill and use its complete structure for defining epics, issues, and tasks."
})
```

**When to use**:
- Planning a new feature or epic
- Breaking down an issue into tasks
- Prioritizing work for a sprint

---

### 2. Pair Programmer Agent

**Purpose**: Master coordinator orchestrating Driver and Navigator to collaboratively resolve issues

**Skills**:
- `pair-programmer` - Coordination workflow
- Spawns `driver` and `navigator` sub-agents as needed

**How to spawn**:
```
Agent({
  description: "Pair Programmer coordinator orchestrating Driver and Navigator agents",
  subagent_type: "pair-programmer",
  prompt: "Load the pair-programmer skill and coordinate issue resolution with Driver and Navigator agents."
})
```

**When to use**:
- Working on an issue from start to finish
- Need coordination between analysis (Navigator) and implementation (Driver)
- Want guaranteed quality through pair programming workflow

**Sub-agents spawned by Pair Programmer**:
- Navigator (Opus model) — analysis and planning
- Driver (Haiku model) — implementation

---

### 3. Navigator Agent

**Purpose**: Deep issue analysis, architectural investigation, and standards compliance supervision

**Skills**:
- `navigator` - Analysis and planning workflow

**How to spawn directly**:
```
Agent({
  description: "Navigator agent for deep issue analysis and architectural planning",
  subagent_type: "navigator",
  model: "opus",
  prompt: "Load the navigator skill and provide comprehensive architectural analysis and implementation plans."
})
```

**When to use**:
- Need architectural analysis before implementation
- Investigating codebase structure
- Planning a complex change
- Reviewing code against standards

**Note**: Typically spawned by Pair Programmer, not used directly.

---

### 4. Driver Agent

**Purpose**: Implement code under Navigator supervision according to established plan

**Skills**:
- `driver` - Implementation workflow

**How to spawn directly**:
```
Agent({
  description: "Driver agent for code implementation under Navigator supervision",
  subagent_type: "driver",
  model: "haiku",
  prompt: "Load the driver skill and implement code according to Navigator's plan."
})
```

**When to use**:
- Have a clear implementation plan from Navigator
- Need focused code implementation
- Writing tests, creating files, modifying code

**Note**: Typically spawned by Pair Programmer, not used directly.

---

### 5. Code Reviewer Agent

**Purpose**: Systematic code analysis and feedback against project conventions and quality standards

**Skills**:
- `reviewer` - Code review workflow

**How to spawn**:
```
Agent({
  description: "Code Reviewer agent for systematic code analysis and feedback",
  subagent_type: "code-reviewer",
  model: "sonnet",
  prompt: "Load the reviewer skill and analyze code against project conventions, architecture, and quality standards."
})
```

**When to use**:
- Reviewing a merge request comprehensively
- Need detailed feedback on code quality
- Architecture compliance verification
- Test coverage analysis

---

## Agent Workflows

### Pair Programming Workflow (Recommended)

```
User Issue
    ↓
Pair Programmer (Sonnet)
    ├─ Spawns Navigator (Opus) → Analysis & Plan
    ├─ Spawns Driver (Haiku) → Implementation
    └─ Coordinates iteration until complete
    ↓
Issue Resolved & Ready for Merge
```

### Individual Agent Usage

```
For Analysis:
User → Navigator (Opus) → Architectural Plan

For Implementation:
User + Plan → Driver (Haiku) → Code Changes

For Review:
User + PR → Code Reviewer (Sonnet) → Feedback

For Planning:
User + Feature → Product Owner (Sonnet) → Epic/Issues
```

---

## Quick Reference

| Agent | Model | Spawning | Use When |
|-------|-------|----------|----------|
| **Product Owner** | Sonnet | Manual / Skill | Planning new work |
| **Pair Programmer** | Sonnet | Manual / Skill | Working on an issue end-to-end |
| **Navigator** | Opus | Auto from PP / Manual | Need analysis & architectural planning |
| **Driver** | Haiku | Auto from PP / Manual | Need focused implementation |
| **Code Reviewer** | Sonnet | Manual / Skill | Reviewing merge requests |

---

## Auto-Spawn Instructions

All skills include auto-spawn instructions:

```bash
# Use a skill directly (includes spawning instructions)
/skill product-owner
/skill pair-programmer
/skill navigator
/skill driver
/skill reviewer
```

Each skill file contains the exact `Agent()` call needed to spawn its corresponding agent if not already active.

---

## Agent Configuration

All agents are configured in `.claude/agents/`:
- `product-owner-agent.md` — Product Owner configuration
- `pair-programmer-agent.md` — Pair Programmer configuration
- `driver-agent.md` — Driver configuration
- `navigator-agent.md` — Navigator configuration
- `code-reviewer-agent.md` — Code Reviewer configuration

Skills are in `.claude/skills/`:
- `product-owner/SKILL.md`
- `pair-programmer/SKILL.md`
- `driver/SKILL.md`
- `navigator/SKILL.md`
- `reviewer/SKILL.md`
- `flutter/SKILL.md` — Flutter frontend conventions

---

## Memory System

Agents use project memory (`.claude/projects/.../memory/`) to maintain context across sessions:
- Understand project history and patterns
- Remember past decisions and conventions
- Build up knowledge about the codebase
- Maintain user preferences and feedback

Memory is automatically managed by each agent.

---

## Working with Multiple Agents

### Pair Programming (Recommended)
1. Spawn Pair Programmer
2. It coordinates Navigator and Driver automatically
3. You supervise from outside or let them work

### Sequential Workflow
1. Spawn Navigator → Get analysis and plan
2. Review the plan
3. Spawn Driver with the plan
4. Driver implements with Navigator supervision
5. Review final result

### Parallel Workflow (Advanced)
1. Spawn Product Owner for epic planning
2. While PO works, spawn Navigator for architectural investigation
3. When plan is ready, spawn Driver for implementation
4. Monitor progress and coordinate if needed

---

## Success Criteria

Each agent completes successfully when:

- **Product Owner**: Clear epics/issues with acceptance criteria
- **Pair Programmer**: Issue completely resolved and ready to merge
- **Navigator**: Implementation plan with architecture and conventions
- **Driver**: Code written per plan, all conventions respected
- **Code Reviewer**: Comprehensive feedback on code quality

See individual skill files for detailed success criteria.
