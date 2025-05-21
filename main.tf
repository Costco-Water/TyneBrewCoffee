terraform {
  required_providers {
    null = {
      source = "hashicorp/null"
    }
  }
}

provider "null" {}

resource "null_resource" "docker_deploy" {
  count = 2

  provisioner "local-exec" {
    command = "echo Deploying TyneBrewCoffee Docker container instance ${count.index + 1}..."
  }

  provisioner "local-exec" {
    command = "docker build -t tynebrew . && docker run -d -p ${8080 + count.index}:80 tynebrew"
  }
}
