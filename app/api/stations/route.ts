import { type NextRequest, NextResponse } from "next/server"

const WORDPRESS_API_URL = "https://noman.ebazarhut.com/wp-json/rail/v1"

export async function GET(request: NextRequest) {
  try {
    const url = `${WORDPRESS_API_URL}/stations`

    console.log("[Next.js API] Proxying stations request to:", url)

    const response = await fetch(url, {
      headers: {
        "Content-Type": "application/json",
      },
      signal: AbortSignal.timeout(10000),
    })

    if (!response.ok) {
      throw new Error(`WordPress API responded with status: ${response.status}`)
    }

    const data = await response.json()
    console.log(`[Next.js API] Found ${data.stations?.length || 0} stations`)

    return NextResponse.json(data)
  } catch (error) {
    console.error("[Next.js API] Stations error:", error)
    return NextResponse.json(
      {
        error: "Failed to fetch stations",
        details: error instanceof Error ? error.message : String(error),
      },
      { status: 500 },
    )
  }
}
