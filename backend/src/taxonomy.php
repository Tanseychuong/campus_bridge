<?php
/**
 * Fixed lists of values (categories, interests, ...) and their display labels.
 * The database uses the short "keys" (e.g. 'on-campus'); pages show the labels.
 */

const INTEREST_LABELS = [
    'computer-science' => 'Computer Science',
    'engineering'      => 'Engineering',
    'entrepreneurship' => 'Entrepreneurship',
    'research'         => 'Research',
    'business'         => 'Business',
    'social-impact'    => 'Social Impact',
    'sustainability'   => 'Sustainability',
    'arts'             => 'Arts & Culture',
    'leadership'       => 'Leadership',
    'health'           => 'Health & Wellbeing',
];

const OPPORTUNITY_CATEGORIES = [
    'scholarship'  => 'Scholarship',
    'internship'   => 'Internship',
    'research'     => 'Research',
    'fellowship'   => 'Fellowship',
    'competition'  => 'Competition',
    'hackathon'    => 'Hackathon',
    'leadership'   => 'Leadership Program',
    'volunteering' => 'Volunteering',
    'career'       => 'Career Development',
];

const EVENT_CATEGORIES = [
    'academic'  => 'Academic',
    'career'    => 'Career',
    'social'    => 'Social',
    'tech'      => 'Tech',
    'arts'      => 'Arts',
    'sports'    => 'Sports',
    'wellness'  => 'Wellness',
    'community' => 'Community',
];

const ORGANIZATION_CATEGORIES = [
    'academic'         => 'Academic',
    'professional'     => 'Professional',
    'tech'             => 'Technology',
    'entrepreneurship' => 'Entrepreneurship',
    'service'          => 'Service & Volunteering',
    'arts'             => 'Arts & Media',
    'sports'           => 'Sports & Fitness',
    'cultural'         => 'Cultural',
];

const LOCATION_MODES = [
    'on-campus' => 'On campus',
    'in-person' => 'In person (off campus)',
    'remote'    => 'Remote',
    'hybrid'    => 'Hybrid',
];

const EVENT_VISIBILITY = [
    'public' => 'Everyone (all campuses)',
    'campus' => 'Only students on our campus',
];

const ROLE_LABELS = [
    'student' => 'Student',
    'org_rep'    => 'Organization rep',
    'campus_rep' => 'Campus rep',
    'admin'      => 'Administrator',
];

/** Turns a list of interest keys into readable labels: "Research, Arts & Culture". */
function interest_labels(array $interestKeys): string
{
    $labels = array_map(fn ($key) => INTEREST_LABELS[$key] ?? $key, $interestKeys);
    return implode(', ', $labels);
}
