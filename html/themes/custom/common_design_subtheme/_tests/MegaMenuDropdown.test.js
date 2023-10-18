import env from './_env'

describe('MegaMenuDropdown', () => {
  beforeAll(async() => {
    await page.goto(env.baseUrl);
    await page.setViewport({ width: 1280, height: 800 });
  });

  it('should expand when clicked', async() => {
    const toggle = await page.$('.uno-mega-menu-item button.cd-nav-level-1__btn');
    await toggle.click();
    const hidden = await page.$eval('.uno-mega-menu', el => el.dataset.cdHidden);
    await expect(hidden).toMatch('false');
  });
});
