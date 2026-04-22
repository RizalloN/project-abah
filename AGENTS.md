# Project Notes

- Before adding or changing MySQL indexes, inspect existing indexes for exact duplicates and left-prefix coverage. Do not add redundant indexes, especially on large `project_abah` tables such as `simpanan_multipn`, because the database has already been optimized and duplicate indexes significantly inflate storage and import cost.
