# Academic management architecture

## Audit summary

Before this refactor, `Course` stored `grade`, `semester`, `category`, and `unit` as unrelated strings. Lessons and media were correctly nested under courses, but paid access depended on an `Enrollment` for every course. Registration subscription orders did not create a durable semester entitlement. Consequently, newly published courses were not inherited by subscribed students and access checks were repeated in several API controllers.

### Risk classification

- **Critical:** semester subscriptions did not represent an academic entitlement; access depended on individual course enrollments.
- **Critical:** authorization checks were duplicated across learning, media, assessment, and review endpoints.
- **High:** free-form academic strings permitted invalid grade/semester/subject combinations.
- **High:** there was no normalized unit entity or lesson publishing window.
- **Improvement:** admin content screens lacked a durable academic breadcrumb and a dedicated structure workspace.
- **Polish:** operational dashboard information and academic navigation needed clearer grouping.

## Target model

```text
AcademicYear
  └─ AcademicGrade
       └─ AcademicTerm
            └─ AcademicSubject ─ Subject
                 ├─ Course
                 └─ Unit
                      └─ Lesson
                           └─ MediaAsset

SubscriptionPlan ──< AcademicTerm
Student ──< StudentSubscription >── AcademicTerm
Student ──< Enrollment >── Course (legacy and one-off purchases)
```

`StudentContentAccessService` is the authoritative access decision. It accepts either an active direct enrollment or an active academic-term subscription, and it additionally enforces course publication, lesson status, and lesson availability windows. Progress remains stored independently in lesson completions, assessment attempts, and workspace state.

## Admin workflow

1. Create or select an academic year.
2. Create/select a grade and its term.
3. Create a reusable subject and attach it to that term.
4. Add ordered units to the subject offering.
5. Assign a course to the offering.
6. Assign lessons to units, keep them as drafts, then publish or schedule them.
7. Define which academic terms each subscription plan grants.

Important relationships are validated in Laravel requests/controllers. Academic records use restrictive foreign keys so content with historical progress cannot be casually deleted.

## Student access flow

```text
Approved student
  → active StudentSubscription
  → AcademicTerm
  → active AcademicSubjects
  → published Courses
  → published/available Lessons
  → ready MediaAssets and published Assessments
```

New courses published into an entitled academic term become available automatically; no per-student lesson or course assignment is needed. Expired or cancelled subscriptions stop granting scope access. Existing direct enrollments remain supported for course and bundle purchases.
