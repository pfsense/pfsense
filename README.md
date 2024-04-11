The main README file should reside in the root of your project. This way, source code hosters like GitHub, GitLab, and Bitbucket know where to find it.

You can create additional README files inside subdirectories. Most websites recognize it as such and will display it below the code repo, just like it would do on the home page of your repository.

README.md links
Creating links in Markdown is covered in the Markdown Cheat Sheet. However, links in README.md files deserve a little more explaining. There are two types of links that you typically want to include in your README file:

Links to external resources, like a documentation website or other related content.
References to files inside your repository, like other Markdown formatted documentation or perhaps directly to source code files.
It’s good to know that links in Markdown get converted to HTML links and that links can be absolute or relative. If you like to learn more about links, read this HTML tutorial on creating links.

Links to URLs
Let’s start with links to other URLs. For this, you just do as is usual in Markdown. For completeness, here are some examples:

1
An absolute link to [Google](https://google.com)
2
​
3
URLs like <https://google.com>, and sometimes https://google.com
4
or even google.com, often get converted to clickable links too.
5
​
6
Inline-style link with a title attribute:
7
[Markdown Land](https://markdown.land "Markdown Land")
Links to files in your repository
A common use case is linking to files inside your repository. Thankfully, because GitHub follows the structure of your repository in their URL, you can use relative links in your Markdown documents. When GitHub parses the Markdown, the resulting HTML page will contain relative links that automatically point to the right files in your repo:

# This is the main repository for project XYZ. 
​
Please see the following resources inside this repo:
​
- [The Backend](./backend)
- [The Docker configuration](./docker)
- [The WordPress plugin](./plugins/wordpress)
- [The website (django)](./webapp)
​
## Documentation
​
The documentation resides in the [docs](./docs/index.md) folder.
​
Installation steps can be found in our
[installation instructions](./docs/first-steps/installation.md)
