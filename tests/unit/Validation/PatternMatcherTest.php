<?php

use Maan511\OpenapiToLaravel\Validation\PatternMatcher;

describe('PatternMatcher', function (): void {
    describe('normalizePattern', function (): void {
        it('adds leading slash to patterns without one', function (): void {
            expect(PatternMatcher::normalizePattern('api/*'))->toBe('/api/*');
            expect(PatternMatcher::normalizePattern('api/users'))->toBe('/api/users');
        });

        it('keeps patterns with leading slash unchanged', function (): void {
            expect(PatternMatcher::normalizePattern('/api/*'))->toBe('/api/*');
            expect(PatternMatcher::normalizePattern('/api/users'))->toBe('/api/users');
        });

        it('keeps wildcard-starting patterns unchanged', function (): void {
            expect(PatternMatcher::normalizePattern('*users*'))->toBe('*users*');
            expect(PatternMatcher::normalizePattern('*/api'))->toBe('*/api');
        });

        it('lowercases patterns for case-insensitive matching', function (): void {
            expect(PatternMatcher::normalizePattern('API/*'))->toBe('/api/*');
            expect(PatternMatcher::normalizePattern('*/Users'))->toBe('*/users');
        });
    });

    describe('normalizePath', function (): void {
        it('adds leading slash to paths without one', function (): void {
            expect(PatternMatcher::normalizePath('api/users'))->toBe('/api/users');
            expect(PatternMatcher::normalizePath('users'))->toBe('/users');
        });

        it('keeps paths with leading slash unchanged', function (): void {
            expect(PatternMatcher::normalizePath('/api/users'))->toBe('/api/users');
        });

        it('removes trailing slash except for root', function (): void {
            expect(PatternMatcher::normalizePath('/api/users/'))->toBe('/api/users');
            expect(PatternMatcher::normalizePath('/'))->toBe('/');
        });

        it('lowercases paths for case-insensitive matching', function (): void {
            expect(PatternMatcher::normalizePath('/API/Users'))->toBe('/api/users');
        });
    });

    describe('matches', function (): void {
        it('matches prefix patterns with or without leading slash', function (): void {
            expect(PatternMatcher::matches('api/*', '/api/users'))->toBeTrue();
            expect(PatternMatcher::matches('/api/*', '/api/users'))->toBeTrue();
            expect(PatternMatcher::matches('api/*', 'api/users'))->toBeTrue();
        });

        it('matches contains patterns', function (): void {
            expect(PatternMatcher::matches('*users*', '/api/users'))->toBeTrue();
            expect(PatternMatcher::matches('*users*', '/admin/users/settings'))->toBeTrue();
            expect(PatternMatcher::matches('*users*', '/v1/api/users'))->toBeTrue();
        });

        it('matches suffix patterns', function (): void {
            expect(PatternMatcher::matches('*/users', '/api/users'))->toBeTrue();
            expect(PatternMatcher::matches('*/users', '/admin/users'))->toBeTrue();
        });

        it('matches mid-path wildcard patterns', function (): void {
            expect(PatternMatcher::matches('/api/*/users', '/api/v1/users'))->toBeTrue();
            expect(PatternMatcher::matches('/api/*/users', '/api/v2/users'))->toBeTrue();
        });

        it('matches exact paths', function (): void {
            expect(PatternMatcher::matches('/api/users', '/api/users'))->toBeTrue();
            expect(PatternMatcher::matches('api/users', '/api/users'))->toBeTrue();
        });

        it('does not match non-matching patterns', function (): void {
            expect(PatternMatcher::matches('api/*', '/admin/users'))->toBeFalse();
            expect(PatternMatcher::matches('*posts*', '/api/users'))->toBeFalse();
        });

        it('is case-insensitive', function (): void {
            expect(PatternMatcher::matches('API/*', '/api/users'))->toBeTrue();
            expect(PatternMatcher::matches('api/*', '/API/USERS'))->toBeTrue();
            expect(PatternMatcher::matches('*Users*', '/api/users'))->toBeTrue();
        });
    });

    describe('matchesAny', function (): void {
        it('returns true if any pattern matches', function (): void {
            $patterns = ['api/*', 'admin/*'];
            expect(PatternMatcher::matchesAny($patterns, '/api/users'))->toBeTrue();
            expect(PatternMatcher::matchesAny($patterns, '/admin/settings'))->toBeTrue();
        });

        it('returns false if no patterns match', function (): void {
            $patterns = ['api/*', 'admin/*'];
            expect(PatternMatcher::matchesAny($patterns, '/public/index'))->toBeFalse();
        });

        it('handles empty pattern array', function (): void {
            expect(PatternMatcher::matchesAny([], '/api/users'))->toBeFalse();
        });
    });

    describe('filter', function (): void {
        it('filters paths by pattern', function (): void {
            $paths = ['/api/users', '/api/posts', '/admin/users'];
            $result = PatternMatcher::filter($paths, 'api/*');

            expect($result)->toHaveCount(2);
            expect($result)->toContain('/api/users');
            expect($result)->toContain('/api/posts');
        });

        it('works with contains patterns', function (): void {
            $paths = ['/api/users', '/v1/users', '/admin/posts'];
            $result = PatternMatcher::filter($paths, '*users*');

            expect($result)->toHaveCount(2);
            expect($result)->toContain('/api/users');
            expect($result)->toContain('/v1/users');
        });
    });

    describe('countMatches', function (): void {
        it('counts matching paths', function (): void {
            $paths = ['/api/users', '/api/posts', '/admin/users'];
            expect(PatternMatcher::countMatches('api/*', $paths))->toBe(2);
            expect(PatternMatcher::countMatches('*users*', $paths))->toBe(2);
            expect(PatternMatcher::countMatches('admin/*', $paths))->toBe(1);
        });
    });

    describe('getSuggestions', function (): void {
        it('suggests adding leading slash when pattern without slash would match', function (): void {
            $paths = ['/api/users', '/api/posts'];
            $suggestions = PatternMatcher::getSuggestions('nonexistent/*', $paths);

            // Should provide suggestions or show available paths
            expect($suggestions)->not->toBeEmpty();
        });

        it('suggests wildcard patterns when exact match fails', function (): void {
            $paths = ['/api/users', '/api/posts'];
            $suggestions = PatternMatcher::getSuggestions('api', $paths);

            expect($suggestions)->not->toBeEmpty();
            // Should suggest wildcard variants
            $suggestionsText = implode(' ', $suggestions);
            expect($suggestionsText)->toContain('*');
        });

        it('shows example paths when no good suggestions', function (): void {
            $paths = ['/api/users', '/api/posts'];
            $suggestions = PatternMatcher::getSuggestions('xyz/*', $paths);

            expect($suggestions)->not->toBeEmpty();
            $suggestionsText = implode(' ', $suggestions);
            expect($suggestionsText)->toContain('/api/');
        });
    });

    describe('validatePatterns', function (): void {
        it('validates well-formed patterns', function (): void {
            $patterns = ['api/*', '/admin/*', '*users*'];
            $result = PatternMatcher::validatePatterns($patterns);

            expect($result['valid'])->toBeTrue();
            expect($result['errors'])->toBeEmpty();
        });

        it('detects empty patterns', function (): void {
            $patterns = ['api/*', '', 'admin/*'];
            $result = PatternMatcher::validatePatterns($patterns);

            expect($result['valid'])->toBeFalse();
            expect($result['errors'])->toContain('Empty pattern provided');
        });

        it('detects invalid characters', function (): void {
            $patterns = ['api/<users>', 'admin/*'];
            $result = PatternMatcher::validatePatterns($patterns);

            expect($result['valid'])->toBeFalse();
            expect($result['errors'][0])->toContain('invalid characters');
        });

        it('warns about trailing slashes', function (): void {
            $patterns = ['api/users/'];
            $result = PatternMatcher::validatePatterns($patterns);

            expect($result['valid'])->toBeFalse();
            expect($result['errors'][0])->toContain('ends with /');
        });
    });

    describe('real-world scenarios', function (): void {
        it('matches Laravel routes regardless of leading slash', function (): void {
            // Laravel routes come without leading slash
            $laravelRoute = 'api/users/{id}';

            expect(PatternMatcher::matches('api/*', $laravelRoute))->toBeTrue();
            expect(PatternMatcher::matches('/api/*', $laravelRoute))->toBeTrue();
        });

        it('matches OpenAPI endpoints with leading slash', function (): void {
            // OpenAPI paths have leading slash
            $openapiPath = '/api/users/{id}';

            expect(PatternMatcher::matches('api/*', $openapiPath))->toBeTrue();
            expect(PatternMatcher::matches('/api/*', $openapiPath))->toBeTrue();
        });

        it('supports complex version-based patterns', function (): void {
            $paths = ['/api/v1/users', '/api/v2/users', '/api/v1/posts'];

            expect(PatternMatcher::matches('*/v1/*', '/api/v1/users'))->toBeTrue();
            expect(PatternMatcher::matches('*/v2/*', '/api/v2/users'))->toBeTrue();
            expect(PatternMatcher::countMatches('*/v1/*', $paths))->toBe(2);
        });

        it('handles parameter placeholders in paths', function (): void {
            expect(PatternMatcher::matches('api/users/*', '/api/users/{id}'))->toBeTrue();
            expect(PatternMatcher::matches('*/users/*', '/api/users/{id}/posts'))->toBeTrue();
        });
    });
});
