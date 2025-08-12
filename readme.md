<a name="readme-top"></a>


<!-- PROJECT LOGO -->
<br />
<div align="center">

<h3 align="center">bookshelf - a reading list manager</h3>

  <p align="center">
    Record when you last carried out something and see stats over time.
    <br />
  </p>
</div>



<!-- TABLE OF CONTENTS -->
<details>
  <summary>Table of Contents</summary>
  <ol>
    <li>
      <a href="#about-the-project">About The Project</a>
      <ul>
        <li><a href="#built-with">Built With</a></li>
      </ul>
    </li>
    <li>
      <a href="#getting-started">Getting Started</a>
      <ul>
        <li><a href="#prerequisites">Prerequisites</a></li>
        <li><a href="#installation">Installation</a></li>
      </ul>
    </li>
    <li><a href="#usage">Usage</a></li>
    <li><a href="#roadmap">Roadmap</a></li>
    <li><a href="#contributing">Contributing</a></li>
    <li><a href="#license">License</a></li>
    <li><a href="#contact">Contact</a></li>
    <li><a href="#acknowledgments">Acknowledgments</a></li>
  </ol>
</details>



<!-- ABOUT THE PROJECT -->
## About The Project

My electric toothbrush seems to be running out of charge quicker and quicker but is it or am I just misremembering when I last charged it? WDiL aims to help solve those sorts of problems by allowing you to record every time you do something and then see stats on things such as frequency and average interval.

![](https://www.spokenlikeageek.com/wp-content/uploads/2024/09/2024-09-14-15-40-48.png)

<p align="right">(<a href="#readme-top">back to top</a>)</p>



### Built With

* [PHP](https://php.net)
* [Boostrap](https://getbootstrap.com/)
* [smarty](https://github.com/smarty-php/smarty)
* [phpMailer](https://github.com/PHPMailer/PHPMailer)

<p align="right">(<a href="#readme-top">back to top</a>)</p>



<!-- GETTING STARTED -->
## Getting Started

Getting up and running is very straightforward:

1. download the code/clone the repository
2. install [composer](https://getcomposer.org/)
3. add the smarty templating engine and phpMailer
    
    > composer.phar require smarty/smarty
    
    > composer.phar require PHPMailer/PHPMailer
4. follow the installation instructions below.


You can read more about how it all works in [these blog posts](https://www.spokenlikeageek.com/tag/when-did-i-last/).

### Prerequisites

Requirements are very simple, it requires the following:

1. PHP (I tested on v8.1.13)
2. [composer] (https://getcomposer.org/)

### Installation

You must install the dependencies, create some required files and set the appropriate permissions. This is what I did but you may need to adjust depending on your flavour of OS:

1. ```git clone https://github.com/williamsdb/WDiL```
2. ```cd WDiL\src```
1. ```mkdir vendor```
2. ```php composer.phar require smarty/smarty```
3. ```php composer.phar require PHPMailer/PHPMailer```
3. ```sudo mkdir templates_c```
4. ```sudo chown apache:apache templates_c -R```
5. ```sudo chcon -R -t httpd_sys_rw_content_t templates_c```
6. ```sudo mv config_dummy.php config.php```
7. ```sudo mkdir databases```
8. ```sudo chown -R apache:apache databases```
9. ```sudo chcon -R -t httpd_sys_rw_content_t databases```
10. ```sudo touch logs.db```
11. ```sudo touch users.db```
12. ```sudo chown apache:apache *.db```
13. ```sudo chcon -R -t httpd_sys_rw_content_t *.db```
14. ```sudo chown apache:apache config.php```
15. ```sudo chcon -R -t httpd_sys_rw_content_t config.php```

<p align="right">(<a href="#readme-top">back to top</a>)</p>



<!-- USAGE EXAMPLES -->
## Usage

_For more information, please refer to the [these blog posts](https://www.spokenlikeageek.com/tag/when-did-i-last/)_

<p align="right">(<a href="#readme-top">back to top</a>)</p>



<!-- ROADMAP -->
## Known Issues

- None

See the [open issues](https://github.com/williamsdb/WDiL/issues) for a full list of proposed features (and known issues).

<p align="right">(<a href="#readme-top">back to top</a>)</p>



<!-- CONTRIBUTING -->
## Contributing

Contributions are what make the open source community such an amazing place to learn, inspire, and create. Any contributions you make are **greatly appreciated**.

If you have a suggestion that would make this better, please fork the repo and create a pull request. You can also simply open an issue with the tag "enhancement".
Don't forget to give the project a star! Thanks again!

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

<p align="right">(<a href="#readme-top">back to top</a>)</p>



<!-- LICENSE -->
## License

Distributed under the GNU General Public License v3.0. See `LICENSE` for more information.

<p align="right">(<a href="#readme-top">back to top</a>)</p>



<!-- CONTACT -->
## Contact

X - [@spokenlikeageek](https://x.com/spokenlikeageek) 

Bluesky - [@spokenlikeageek.com](https://bsky.app/profile/spokenlikeageek.com)

Mastodon - [@spokenlikeageek](https://techhub.social/@spokenlikeageek)

Website - [https://spokenlikeageek.com](https://www.spokenlikeageek.com/tag/when-did-i-last/)


Project link - [Github](https://github.com/williamsdb/WDiL)

<p align="right">(<a href="#readme-top">back to top</a>)</p>



<!-- ACKNOWLEDGMENTS -->
## Acknowledgments

* None

<p align="right">(<a href="#readme-top">back to top</a>)</p>



<!-- MARKDOWN LINKS & IMAGES -->
<!-- https://www.markdownguide.org/basic-syntax/#reference-style-links -->
[contributors-shield]: https://img.shields.io/github/contributors/github_username/repo_name.svg?style=for-the-badge
[contributors-url]: https://github.com/github_username/repo_name/graphs/contributors
[forks-shield]: https://img.shields.io/github/forks/github_username/repo_name.svg?style=for-the-badge
[forks-url]: https://github.com/github_username/repo_name/network/members
[stars-shield]: https://img.shields.io/github/stars/github_username/repo_name.svg?style=for-the-badge
[stars-url]: https://github.com/github_username/repo_name/stargazers
[issues-shield]: https://img.shields.io/github/issues/github_username/repo_name.svg?style=for-the-badge
[issues-url]: https://github.com/github_username/repo_name/issues
[license-shield]: https://img.shields.io/github/license/github_username/repo_name.svg?style=for-the-badge
[license-url]: https://github.com/github_username/repo_name/blob/master/LICENSE.txt
[linkedin-shield]: https://img.shields.io/badge/-LinkedIn-black.svg?style=for-the-badge&logo=linkedin&colorB=555
[linkedin-url]: https://linkedin.com/in/linkedin_username
[product-screenshot]: images/screenshot.png
[Next.js]: https://img.shields.io/badge/next.js-000000?style=for-the-badge&logo=nextdotjs&logoColor=white
[Next-url]: https://nextjs.org/
[React.js]: https://img.shields.io/badge/React-20232A?style=for-the-badge&logo=react&logoColor=61DAFB
[React-url]: https://reactjs.org/
[Vue.js]: https://img.shields.io/badge/Vue.js-35495E?style=for-the-badge&logo=vuedotjs&logoColor=4FC08D
[Vue-url]: https://vuejs.org/
[Angular.io]: https://img.shields.io/badge/Angular-DD0031?style=for-the-badge&logo=angular&logoColor=white
[Angular-url]: https://angular.io/
[Svelte.dev]: https://img.shields.io/badge/Svelte-4A4A55?style=for-the-badge&logo=svelte&logoColor=FF3E00
[Svelte-url]: https://svelte.dev/
[Laravel.com]: https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white
[Laravel-url]: https://laravel.com
[Bootstrap.com]: https://img.shields.io/badge/Bootstrap-563D7C?style=for-the-badge&logo=bootstrap&logoColor=white
[Bootstrap-url]: https://getbootstrap.com
[JQuery.com]: https://img.shields.io/badge/jQuery-0769AD?style=for-the-badge&logo=jquery&logoColor=white
[JQuery-url]: https://jquery.com 
