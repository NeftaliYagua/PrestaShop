<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace PrestaShop\PrestaShop\Core\Category\NameBuilder;

use Cache;
use PrestaShop\PrestaShop\Adapter\Category\Repository\CategoryRepository;
use PrestaShop\PrestaShop\Core\Domain\Category\ValueObject\CategoryId;
use PrestaShop\PrestaShop\Core\Domain\Language\ValueObject\LanguageId;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopId;

/**
 * Builds category display name to avoid confusing categories when they have identical names.
 *
 * If there are multiple categories with identical names, we want to be able to tell them apart,
 * so we use breadcrumb path instead of category name.
 *
 * e.g. "Clothes > Women"
 */
class CategoryDisplayNameBuilder
{
    /**
     * @var CategoryRepository
     */
    private $categoryRepository;

    /**
     * @var string
     */
    private $breadcrumbSeparator;

    /**
     * @param CategoryRepository $categoryRepository
     * @param string $breadcrumbSeparator
     */
    public function __construct(
        CategoryRepository $categoryRepository,
        string $breadcrumbSeparator
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->breadcrumbSeparator = $breadcrumbSeparator;
    }

    /**
     * @param string $categoryName
     * @param ShopId $shopId
     * @param LanguageId $languageId
     * @param CategoryId $categoryId
     * @param bool $useCache
     *
     * @return string
     */
    public function build(
        string $categoryName,
        ShopId $shopId,
        LanguageId $languageId,
        CategoryId $categoryId,
        bool $useCache = true
    ): string {
        $duplicateNames = $this->getDuplicateNames($shopId, $languageId, $useCache);

        if (!in_array($categoryName, $duplicateNames, true)) {
            return $categoryName;
        }

        $breadcrumbParts = $this->categoryRepository->getBreadcrumbParts($categoryId, $languageId);

        return $this->buildBreadcrumb($breadcrumbParts);
    }

    /**
     * In some cases we already have breadcrumbs before calling this method, so we allow providing them here for optimization purposes
     *
     * @param string $categoryName
     * @param ShopId $shopId
     * @param LanguageId $languageId
     * @param string[] $breadcrumbParts
     * @param bool $useCache
     *
     * @return string
     */
    public function buildWithBreadcrumbs(
        string $categoryName,
        ShopId $shopId,
        LanguageId $languageId,
        array $breadcrumbParts,
        bool $useCache = true
    ): string {
        $duplicateNames = $this->getDuplicateNames($shopId, $languageId, $useCache);

        if (!in_array($categoryName, $duplicateNames, true)) {
            return $categoryName;
        }

        return $this->buildBreadcrumb($breadcrumbParts);
    }

    /**
     * @param ShopId $shopId
     * @param LanguageId $langId
     *
     * @return string
     */
    private function buildCacheKey(ShopId $shopId, LanguageId $langId): string
    {
        return sprintf(
            'Category::duplicateCategoryNames_shop_%s_lang_%s',
            $shopId->getValue(),
            $langId->getValue()
        );
    }

    /**
     * @param ShopId $shopId
     * @param LanguageId $languageId
     * @param bool $useCache
     *
     * @return string[]
     */
    private function getDuplicateNames(ShopId $shopId, LanguageId $languageId, bool $useCache): array
    {
        if (!$useCache) {
            return $this->categoryRepository->getDuplicateNames($shopId, $languageId);
        }

        $cacheKey = $this->buildCacheKey($shopId, $languageId);

//      @todo: consider using Symfony\Component\Cache\Adapter\AdapterInterface instead of legacy Cache
        if (Cache::isStored($this->buildCacheKey($shopId, $languageId))) {
            return Cache::retrieve($cacheKey);
        }

        $duplicateNames = $this->categoryRepository->getDuplicateNames($shopId, $languageId);
        Cache::store($cacheKey, $duplicateNames);

        return $duplicateNames;
    }

    /**
     * Whole breadcrumb path would probably be too long, therefore not UX friendly.
     * Calculating "optimal" breadcrumb length seems too complex compared to the value it could bring.
     * So, we show one parent name and category name, as it is simple and should cover most cases.
     *
     * @param string[] $breadcrumbParts
     *
     * @return string
     */
    private function buildBreadcrumb(array $breadcrumbParts): string
    {
        return implode($this->breadcrumbSeparator, array_slice($breadcrumbParts, -2, 2));
    }
}
