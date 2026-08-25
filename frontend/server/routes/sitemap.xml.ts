import { defineEventHandler, getRequestHost, getRequestProtocol, setHeader } from 'h3'

export default defineEventHandler((event) => {
  const { public: { siteUrl } } = useRuntimeConfig()

  const host = getRequestHost(event, { xForwardedHost: true })
  const protocol = getRequestProtocol(event, { xForwardedProto: true })
  const baseUrl = (siteUrl || `${protocol}://${host}`).replace(/\/+$/, '')
  const now = new Date().toISOString()

  const urls = [
    '/',
  ]

  const urlNodes = urls
    .map((path) => {
      return [
        '  <url>',
        `    <loc>${baseUrl}${path}</loc>`,
        `    <lastmod>${now}</lastmod>`,
        '    <changefreq>weekly</changefreq>',
        '    <priority>1.0</priority>',
        '  </url>',
      ].join('\n')
    })
    .join('\n')

  const xml = [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
    urlNodes,
    '</urlset>',
  ].join('\n')

  setHeader(event, 'content-type', 'application/xml; charset=UTF-8')
  return xml
})
