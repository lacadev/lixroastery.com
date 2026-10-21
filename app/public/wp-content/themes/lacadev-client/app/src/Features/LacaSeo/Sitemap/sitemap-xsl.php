<?php
/**
 * The XSL stylesheet output for Laca SEO XML Sitemaps.
 */

use App\Features\LacaSeo\Sitemap\Router;

defined('ABSPATH') || exit;

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<xsl:stylesheet version="2.0"
	xmlns:html="http://www.w3.org/TR/REC-html40"
	xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
	xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
	xmlns:kml="http://www.opengis.net/kml/2.2"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:atom="http://www.w3.org/2005/Atom">
<xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>
	<xsl:template match="/">
		<html xmlns="http://www.w3.org/1999/xhtml">
			<head>
				<title><?php echo esc_html($title ?? 'XML Sitemap'); ?></title>
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
				<style type="text/css">
					body {
						font-size: 14px;
						font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;
						margin: 0;
						color: #545353;
						background-color: #f7f7f7;
					}
					a {
						color: #05809e;
						text-decoration: none;
					}
					a:hover {
						text-decoration: underline;
					}
					h1 {
						font-size: 24px;
						font-family: inherit;
						font-weight: 600;
						margin: 0;
					}
					#description {
						background-color: #1e293b;
						padding: 30px 40px;
						color: #fff;
					}
					#description h1,
					#description p,
					#description a {
						color: #fff;
						margin: 0;
						font-size: 1.05em;
					}
					#description h1 {
						font-size: 1.8em;
						margin-bottom: 0.5em;
					}
					#description p {
						margin-top: 5px;
						color: #cbd5e1;
					}
					#content {
						padding: 20px 40px;
						background: #fff;
						max-width: 90%;
						margin: 20px auto;
						box-shadow: 0 1px 3px rgba(0,0,0,0.1);
						border-radius: 6px;
					}
					table {
						border: none;
						border-collapse: collapse;
						font-size: .95em;
						width: 100%;
					}
					th {
						background-color: #f1f5f9;
						color: #334155;
						text-align: left;
						padding: 12px 10px;
						font-size: 13px;
						font-weight: 600;
						border-bottom: 2px solid #cbd5e1;
					}
					td {
						padding: 10px;
						border-bottom: 1px solid #e2e8f0;
					}
					tbody tr:nth-child(even) {
						background-color: #f8fafc;
					}
					tbody tr:hover {
						background-color: #f1f5f9;
					}
					.expl {
						margin-bottom: 15px;
						font-size: 13px;
						color: #64748b;
					}
				</style>
			</head>
			<body>
				<div id="description">
					<h1><?php esc_html_e('XML Sitemap', 'lacadev'); ?></h1>
					<p><?php esc_html_e('Sơ đồ trang XML được tối ưu bởi Laca SEO Framework. Tệp này cung cấp cấu trúc liên kết cho công cụ tìm kiếm Google, Bing thu thập dữ liệu nhanh chóng.', 'lacadev'); ?></p>
				</div>

				<div id="content">
					<xsl:if test="count(sitemap:sitemapindex/sitemap:sitemap) &gt; 0">
						<p class="expl">
							Tệp danh mục Sitemap Index chứa <strong><xsl:value-of select="count(sitemap:sitemapindex/sitemap:sitemap)"/></strong> sơ đồ trang con.
						</p>

						<table id="sitemap" cellpadding="3">
							<thead>
								<tr>
									<th width="75%">Sitemap con</th>
									<th width="25%">Cập nhật lần cuối (Last Modified)</th>
								</tr>
							</thead>
							<tbody>
								<xsl:for-each select="sitemap:sitemapindex/sitemap:sitemap">
									<xsl:variable name="sitemapURL">
										<xsl:value-of select="sitemap:loc"/>
									</xsl:variable>
									<tr>
										<td>
											<a href="{$sitemapURL}"><xsl:value-of select="sitemap:loc"/></a>
										</td>
										<td>
											<xsl:value-of select="concat(substring(sitemap:lastmod,0,11),concat(' ', substring(sitemap:lastmod,12,5)),concat(' ', substring(sitemap:lastmod,20,6)))"/>
										</td>
									</tr>
								</xsl:for-each>
							</tbody>
						</table>
					</xsl:if>

					<xsl:if test="count(sitemap:sitemapindex/sitemap:sitemap) &lt; 1">
						<p class="expl">
							Sơ đồ trang con này chứa <strong><xsl:value-of select="count(sitemap:urlset/sitemap:url)"/></strong> liên kết URL.
						</p>
						<p class="expl">
							<a href="<?php echo esc_url(Router::getBaseUrl('sitemap_index.xml')); ?>">&#8592; Quay lại Sitemap Index</a>
						</p>

						<table id="sitemap" cellpadding="3">
							<thead>
								<tr>
									<th width="70%">Địa chỉ liên kết (URL)</th>
									<th width="10%">Hình ảnh</th>
									<th width="20%">Cập nhật lần cuối</th>
								</tr>
							</thead>
							<tbody>
								<xsl:for-each select="sitemap:urlset/sitemap:url">
									<tr>
										<td>
											<xsl:variable name="itemURL">
												<xsl:value-of select="sitemap:loc"/>
											</xsl:variable>
											<a href="{$itemURL}">
												<xsl:value-of select="sitemap:loc"/>
											</a>
										</td>
										<td>
											<xsl:value-of select="count(image:image)"/>
										</td>
										<td>
											<xsl:value-of select="concat(substring(sitemap:lastmod,0,11),concat(' ', substring(sitemap:lastmod,12,5)),concat(' ', substring(sitemap:lastmod,20,6)))"/>
										</td>
									</tr>
								</xsl:for-each>
							</tbody>
						</table>
					</xsl:if>
				</div>
			</body>
		</html>
	</xsl:template>
</xsl:stylesheet>
