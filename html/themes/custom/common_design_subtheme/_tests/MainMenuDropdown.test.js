import env from './_env';

describe('MainMenuDropdown', () => {
  beforeAll(async () => {
    await page.goto(env.baseUrl);
    await page.setViewport({width: 1280, height: 800});
  });

  page.waitForSelector('ul li:not(.uno-mega-menu-item) button.cd-nav-level-1__btn')
  .then(it('should expand when clicked', async () => {
    const toggle = await page.$('ul li:not(.uno-mega-menu-item) button.cd-nav-level-1__btn');
    await toggle.click();
    const hidden = await page.$eval('.cd-nav-level-2__dropdown', el => el.dataset.cdHidden);
    await expect(hidden).toMatch('false');
  }));
});
