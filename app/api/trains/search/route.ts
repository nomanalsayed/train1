import { type NextRequest, NextResponse } from "next/server"

const WORDPRESS_API_URL = "https://noman.ebazarhut.com/wp-json/rail/v1"

export async function GET(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url)
    const query = searchParams.get("query")
    const from = searchParams.get("from")
    const to = searchParams.get("to")

    const url = new URL(`${WORDPRESS_API_URL}/trains/search`)
    if (query) url.searchParams.set("query", query)
    if (from) url.searchParams.set("from", from)
    if (to) url.searchParams.set("to", to)

    console.log("[Next.js API] Proxying train search to:", url.toString())

    const response = await fetch(url.toString(), {
      headers: {
        "Content-Type": "application/json",
      },
      signal: AbortSignal.timeout(10000),
    })

    if (!response.ok) {
      throw new Error(`WordPress API responded with status: ${response.status}`)
    }

    const data = await response.json()
    console.log(`[Next.js API] Found ${data.trains?.length || 0} trains`)

    return NextResponse.json(data)
  } catch (error) {
    console.error("[Next.js API] Train search error:", error)
    return NextResponse.json(
      {
        error: "Failed to search trains",
        details: error instanceof Error ? error.message : String(error),
      },
      { status: 500 },
    )
  }
}
