import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const testTitle = 'Test Post';
const testContent = 'This is a test post.';

test.describe('Create Post', () => {
	test('Ensure post type is properly registered', async ({
		requestUtils,
	}) => {
		const posts = await requestUtils.rest({
			path: '/wp/v2/posts',
			method: 'GET',
		});
		expect(posts).toBeDefined();
	});

	test('Post created', async ({ admin, editor, requestUtils, page }) => {
		// Create a new post.
		await admin.createNewPost({
			title: testTitle,
			content: testContent,
			postType: 'post',
		});
		// Publish the post.
		await editor.publishPost();

		// Get the created homepage via REST API.
		const posts = await requestUtils.rest({
			path: '/wp/v2/posts',
			method: 'GET',
		});
		// Get the first item out of the posts array.
		const post = posts?.[0];
		// Verify the post was created with correct title and content.
		expect(post.title.rendered).toBe(testTitle);
		// Create a screenshot of the post.
		const today = new Date();
		// This gives 'YYYY-MM-DD' format.
		const formattedDate = today.toISOString().split('T')[0];
		await page.screenshot({
			path: `tests/screenshots/post-${formattedDate}.png`,
		});
	});
});
