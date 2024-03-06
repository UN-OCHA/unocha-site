import env from './_env';

describe('MobileMenu', () => {
  beforeAll(async () => {
    await page.goto(env.baseUrl);
  });

  page.waitForSelector('.cd-nav-level-0__btn')
  .then(it('should expand when clicked', async () => {
    const toggle = await page.$('.cd-nav-level-0__btn');
    await toggle.click();
    const hidden = await page.$eval('.cd-site-header__nav', el => el.dataset.cdHidden);
    await expect(hidden).toMatch('false');
  }));

  page.waitForSelector('ul li:not(.uno-mega-menu-item) button.cd-nav-level-1__btn')
  .then(it('should expand when clicked', async () => {
    const toggle = await page.$('ul li:not(.uno-mega-menu-item) button.cd-nav-level-1__btn');
    await toggle.click();
    const hidden = await page.$eval('.cd-nav-level-2__dropdown', el => el.dataset.cdHidden);
    await expect(hidden).toMatch('false');
  }));

  // Mega Menu.
  page.waitForSelector('ul li.uno-mega-menu-item button.cd-nav-level-1__btn')
  .then(it('should expand when clicked', async () => {
    const toggle = await page.$('ul li.uno-mega-menu-item button.cd-nav-level-1__btn');
    await toggle.click();
    const hidden = await page.$eval('.uno-mega-menu', el => el.dataset.cdHidden);
    await expect(hidden).toMatch('false');
  }));

  page.waitForSelector('ul li.uno-mega-menu-item button.cd-nav-level-2__btn')
  .then(it('should expand when clicked', async () => {
    const toggle = await page.$('ul li.uno-mega-menu-item button.cd-nav-level-2__btn');
    await toggle.click();
    const hidden = await page.$eval('.uno-mega-menu__dropdown', el => el.dataset.cdHidden);
    await expect(hidden).toMatch('false');
  }));
});
